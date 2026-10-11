<?php

declare(strict_types=1);

namespace Dock\Ray\Inventory;

use Dock\Ray\AuthData;
use Dock\Ray\Options;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/** Explicit opt-in; call from cron/CLI, never from the page-rendering path. */
final class InventoryReporter
{
    private Options $options;

    private ComposerInventory $collector;

    private string $stateDirectory;

    private string $platform;

    private HttpClientInterface $http;

    private LoggerInterface $logger;

    public function __construct(Options $options, string $projectDirectory, ?string $stateDirectory = null,
        string $platform = 'php', ?HttpClientInterface $http = null, ?LoggerInterface $logger = null)
    {
        $this->options = $options;
        $this->collector = new ComposerInventory($projectDirectory, $options->getInventoryExcludedPackages());
        $this->stateDirectory = $stateDirectory ?? sys_get_temp_dir().'/dockray-inventory-'.(function_exists('posix_geteuid') ? posix_geteuid() : substr(hash('sha256', $projectDirectory), 0, 16));
        $this->platform = $platform;
        $this->http = $http ?? HttpClient::create();
        $this->logger = $logger ?? new NullLogger;
    }

    /** Returns sanitized status only; never response bodies, credentials or file paths. */
    public function report(bool $force = false): array
    {
        if (! $this->options->isInventoryEnabled()) {
            return ['status' => 'disabled'];
        }
        $auth = $this->options->getAuthData();
        if ($auth === null) {
            return ['status' => 'not_configured'];
        }
        $lock = null;
        try {
            if ($auth->getScheme() !== 'https' || preg_match('/[\x00-\x20\x7f]/', $auth->getPrivateKey())) {
                return ['status' => 'invalid_configuration'];
            }
            $url = $this->options->getInventorySiteUrl();
            if ($url === null) {
                return ['status' => 'not_configured'];
            }
            $lock = $this->lock($auth, $url);
            if ($lock === null) {
                return ['status' => 'busy'];
            }
            $raw = stream_get_contents($lock, 8193);
            $state = strlen($raw) <= 8192 ? (json_decode($raw, true) ?: []) : [];
            if (! is_array($state)) {
                $state = [];
            }
            if ((int) ($state['retry_at'] ?? 0) > time()) {
                return ['status' => 'backoff'];
            }
            $payload = $this->collector->collect($url, $this->platform);
            $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (strlen($body) > 512000) {
                throw new \RuntimeException('Inventory exceeds the payload limit.');
            }
            $hash = hash('sha256', $body);
            if (! $force && ($state['hash'] ?? '') === $hash && (int) ($state['next_at'] ?? 0) > time()) {
                return ['status' => 'not_due'];
            }
            // Persist a retry gate before HTTP so an interrupted process cannot flood the service.
            $state['retry_at'] = time() + 300;
            $this->save($lock, $state);
            $response = $this->http->request('POST', $auth->getInventoryApiEndpointUrl(), [
                'headers' => ['Authorization' => 'Bearer '.$auth->getPrivateKey(), 'Content-Type' => 'application/json', 'Accept' => 'application/json'],
                'body' => $body, 'max_redirects' => 0, 'verify_peer' => true, 'verify_host' => true,
                'timeout' => 5, 'max_duration' => 8, 'buffer' => false,
            ]);
            $status = $response->getStatusCode();
            if ($status !== 200) {
                $headers = $response->getHeaders(false);
                $response->cancel();
                $retry = (int) ($headers['retry-after'][0] ?? 0);
                $state['retry_at'] = time() + min(86400, max(in_array($status, [401, 403, 404, 429], true) ? 3600 : 300, $retry));
                $this->save($lock, $state);

                return ['status' => 'failed', 'http_status' => $status];
            }
            $contents = '';
            foreach ($this->http->stream($response) as $chunk) {
                $contents .= $chunk->getContent();
                if (strlen($contents) > 65536) {
                    $response->cancel();
                    throw new \RuntimeException('Inventory response exceeds the limit.');
                }
            }
            $result = json_decode($contents, true);
            if (! is_array($result) || ($result['success'] ?? false) !== true) {
                throw new \RuntimeException('Inventory response is invalid.');
            }
            $interval = min(604800, max(3600, (int) ($result['next_report_in'] ?? 43200)));
            $this->save($lock, ['hash' => $hash, 'next_at' => time() + $interval, 'retry_at' => 0]);

            return ['status' => 'sent', 'components' => count($payload['components']), 'vulnerable' => max(0, (int) ($result['vulnerable'] ?? 0))];
        } catch (Throwable $exception) {
            // Do not attach the exception: transport messages can include URLs or secrets.
            $this->logger->warning('DockRay software inventory could not be sent.');

            return ['status' => 'failed'];
        } finally {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    private function lock(AuthData $auth, string $siteUrl)
    {
        if (! is_dir($this->stateDirectory) && ! @mkdir($this->stateDirectory, 0700, true) && ! is_dir($this->stateDirectory)) {
            throw new \RuntimeException('Inventory state is unavailable.');
        }
        if (is_link($this->stateDirectory) || (fileperms($this->stateDirectory) & 0077) !== 0) {
            throw new \RuntimeException('Inventory state directory must be private.');
        }
        $file = $this->stateDirectory.'/'.hash('sha256', $auth->getServerUrl().'|'.$auth->getToken().'|'.$siteUrl).'.json';
        if (is_link($file)) {
            throw new \RuntimeException('Invalid inventory state file.');
        }
        $handle = @fopen($file, 'c+b');
        if ($handle === false) {
            throw new \RuntimeException('Inventory state is unavailable.');
        }
        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }
        if (! chmod($file, 0600)) {
            flock($handle, LOCK_UN);
            fclose($handle);
            throw new \RuntimeException('Inventory state permissions could not be set.');
        }

        return $handle;
    }

    private function save($handle, array $state): void
    {
        $body = json_encode($state, JSON_THROW_ON_ERROR);
        rewind($handle);
        if (! ftruncate($handle, 0) || fwrite($handle, $body) !== strlen($body) || ! fflush($handle)) {
            throw new \RuntimeException('Inventory state could not be stored.');
        }
    }
}
