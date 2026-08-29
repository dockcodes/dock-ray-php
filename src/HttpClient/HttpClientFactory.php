<?php

declare(strict_types=1);

namespace Dock\Thor\HttpClient;

use Dock\Thor\HttpClient\Authentication\ThorAuthentication;
use Dock\Thor\HttpClient\Plugin\GzipEncoderPlugin;
use Dock\Thor\Options;
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

    public function __construct(
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ?HttpAsyncClientInterface $httpClient,
        private readonly string $sdkIdentifier,
        private readonly string $sdkVersion,
    ) {}

    public function create(Options $options): HttpAsyncClientInterface
    {
        if (null === $options->getAuthData()) {
            throw new \RuntimeException('Cannot create an HTTP client without the DockTHOR credentials set in the options.');
        }

        if (null !== $this->httpClient && null !== $options->getHttpProxy()) {
            throw new \RuntimeException('The "http_proxy" option does not work together with a custom HTTP client.');
        }

        $plugins = [
            new HeaderSetPlugin(['User-Agent' => $this->sdkIdentifier . '/' . $this->sdkVersion]),
            new AuthenticationPlugin(new ThorAuthentication($options)),
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

        if (class_exists(SymfonyHttplugClient::class)) {
            $config = ['max_duration' => self::DEFAULT_HTTP_TIMEOUT];

            if (null !== $proxy) {
                $config['proxy'] = $proxy;
            }

            return new SymfonyHttplugClient(SymfonyHttpClient::create($config));
        }

        if (class_exists(GuzzleHttpClient::class)) {
            $config = [
                GuzzleHttpClientOptions::TIMEOUT => self::DEFAULT_HTTP_TIMEOUT,
                GuzzleHttpClientOptions::CONNECT_TIMEOUT => self::DEFAULT_HTTP_CONNECT_TIMEOUT,
            ];

            if (null !== $proxy) {
                $config[GuzzleHttpClientOptions::PROXY] = $proxy;
            }

            return GuzzleHttpClient::createWithConfig($config);
        }

        if (class_exists(CurlHttpClient::class)) {
            $config = [
                \CURLOPT_TIMEOUT => self::DEFAULT_HTTP_TIMEOUT,
                \CURLOPT_CONNECTTIMEOUT => self::DEFAULT_HTTP_CONNECT_TIMEOUT,
            ];

            if (null !== $proxy) {
                $config[\CURLOPT_PROXY] = $proxy;
            }

            return new CurlHttpClient(null, null, $config);
        }

        if (null !== $proxy) {
            throw new \RuntimeException('The "http_proxy" option requires either the "php-http/curl-client" or the "php-http/guzzle7-adapter" package to be installed.');
        }

        return HttpAsyncClientDiscovery::find();
    }
}
