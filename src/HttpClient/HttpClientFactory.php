<?php

declare(strict_types=1);

namespace Dock\Ray\HttpClient;

use Dock\Ray\HttpClient\Authentication\RayAuthentication;
use Dock\Ray\HttpClient\Plugin\GzipEncoderPlugin;
use Dock\Ray\Options;
use GuzzleHttp\RequestOptions as GuzzleHttpClientOptions;
use Http\Adapter\Guzzle7\Client as GuzzleHttpClient;
use Http\Client\Common\Plugin\AuthenticationPlugin;
use Http\Client\Common\Plugin\DecoderPlugin;
use Http\Client\Common\Plugin\ErrorPlugin;
use Http\Client\Common\Plugin\HeaderSetPlugin;
use Http\Client\Common\Plugin\RetryPlugin;
use Http\Client\Common\PluginClient;
use Http\Client\Curl\Client as CurlHttpClient;
use Http\Client\HttpAsyncClient as HttpAsyncClientInterface;
use Http\Discovery\HttpAsyncClientDiscovery;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;
use Symfony\Component\HttpClient\HttplugClient as SymfonyHttplugClient;

final class HttpClientFactory implements HttpClientFactoryInterface
{
    private const DEFAULT_HTTP_TIMEOUT = 5;

    private const DEFAULT_HTTP_CONNECT_TIMEOUT = 2;

    private StreamFactoryInterface $streamFactory;

    private ?HttpAsyncClientInterface $httpClient;

    private string $sdkIdentifier;

    private string $sdkVersion;

    public function __construct(
        StreamFactoryInterface $streamFactory,
        ?HttpAsyncClientInterface $httpClient,
        string $sdkIdentifier,
        string $sdkVersion
    ) {
        $this->streamFactory = $streamFactory;
        $this->httpClient = $httpClient;
        $this->sdkIdentifier = $sdkIdentifier;
        $this->sdkVersion = $sdkVersion;
    }

    public function create(Options $options): HttpAsyncClientInterface
    {
        if (null === $options->getAuthData()) {
            throw new \RuntimeException('Cannot create an HTTP client without the DockRay credentials set in the options.');
        }

        if (null !== $this->httpClient && null !== $options->getHttpProxy()) {
            throw new \RuntimeException('The "http_proxy" option does not work together with a custom HTTP client.');
        }

        $plugins = [
            new HeaderSetPlugin(['User-Agent' => $this->sdkIdentifier . '/' . $this->sdkVersion]),
            new AuthenticationPlugin(new RayAuthentication($options)),
            new RetryPlugin(['retries' => $options->getSendAttempts()]),
            new ErrorPlugin(),
        ];

        if ($options->isCompressionEnabled()) {
            $plugins[] = new GzipEncoderPlugin($this->streamFactory);
            $plugins[] = new DecoderPlugin();
        }

        return new PluginClient($this->httpClient ?? $this->discoverHttpClient($options), $plugins);
    }

    private function discoverHttpClient(Options $options): HttpAsyncClientInterface
    {
        $proxy = $options->getHttpProxy();

        $client = $this->trySymfonyClient($proxy)
            ?? $this->tryGuzzleClient($proxy)
            ?? $this->tryCurlClient($proxy);

        if (null !== $client) {
            return $client;
        }

        if (null !== $proxy) {
            throw new \RuntimeException('The "http_proxy" option requires either the "php-http/curl-client" or the "php-http/guzzle7-adapter" package to be installed.');
        }

        try {
            return HttpAsyncClientDiscovery::find();
        } catch (\Throwable $exception) {
            throw new \RuntimeException('DockRay found no usable HTTP client. The SDK ships with symfony/http-client; on PHP 7.4 that client additionally needs the "php-http/message-factory" package. Installing "php-http/curl-client" or "php-http/guzzle7-adapter" also resolves this.', 0, $exception);
        }
    }

    /**
     * Żaden kandydat nie ma prawa wywrócić aplikacji.
     *
     * `class_exists()` uruchamia autoloader, a niektóre klasy klientów rzucają
     * wyjątek już przy wczytaniu pliku, gdy brakuje ich własnej zależności —
     * tak robi `HttplugClient` z symfony/http-client 5.4 bez pakietu
     * `php-http/message-factory`. Niekompletna instalacja jednego klienta ma
     * zejść na następnego, a nie zabić żądanie, które monitorujemy.
     */
    private function trySymfonyClient(?string $proxy): ?HttpAsyncClientInterface
    {
        try {
            if (!class_exists(SymfonyHttplugClient::class)) {
                return null;
            }

            $config = ['max_duration' => self::DEFAULT_HTTP_TIMEOUT];

            if (null !== $proxy) {
                $config['proxy'] = $proxy;
            }

            return new SymfonyHttplugClient(SymfonyHttpClient::create($config));
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function tryGuzzleClient(?string $proxy): ?HttpAsyncClientInterface
    {
        try {
            if (!class_exists(GuzzleHttpClient::class)) {
                return null;
            }

            $config = [
                GuzzleHttpClientOptions::TIMEOUT => self::DEFAULT_HTTP_TIMEOUT,
                GuzzleHttpClientOptions::CONNECT_TIMEOUT => self::DEFAULT_HTTP_CONNECT_TIMEOUT,
            ];

            if (null !== $proxy) {
                $config[GuzzleHttpClientOptions::PROXY] = $proxy;
            }

            return GuzzleHttpClient::createWithConfig($config);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function tryCurlClient(?string $proxy): ?HttpAsyncClientInterface
    {
        try {
            if (!class_exists(CurlHttpClient::class)) {
                return null;
            }

            $config = [
                \CURLOPT_TIMEOUT => self::DEFAULT_HTTP_TIMEOUT,
                \CURLOPT_CONNECTTIMEOUT => self::DEFAULT_HTTP_CONNECT_TIMEOUT,
            ];

            if (null !== $proxy) {
                $config[\CURLOPT_PROXY] = $proxy;
            }

            return new CurlHttpClient(null, null, $config);
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
