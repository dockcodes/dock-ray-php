<?php

declare(strict_types=1);

namespace Dock\Ray\Transport;

use Dock\Ray\HttpClient\HttpClientFactoryInterface;
use Dock\Ray\Options;
use Dock\Ray\Serializer\PayloadSerializer;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

final class DefaultTransportFactory implements TransportFactoryInterface
{
    private StreamFactoryInterface $streamFactory;

    private RequestFactoryInterface $requestFactory;

    private HttpClientFactoryInterface $httpClientFactory;

    private ?LoggerInterface $logger;

    public function __construct(
        StreamFactoryInterface $streamFactory,
        RequestFactoryInterface $requestFactory,
        HttpClientFactoryInterface $httpClientFactory,
        ?LoggerInterface $logger = null
    ) {
        $this->streamFactory = $streamFactory;
        $this->requestFactory = $requestFactory;
        $this->httpClientFactory = $httpClientFactory;
        $this->logger = $logger;
    }

    public function create(Options $options): TransportInterface
    {
        if (null === $options->getAuthData()) {
            return new NullTransport();
        }

        $transport = new HttpTransport(
            $options,
            $this->httpClientFactory->create($options),
            $this->streamFactory,
            $this->requestFactory,
            new PayloadSerializer(),
            $this->logger
        );

        return $options->shouldSendAfterResponse()
            ? new DeferredTransport($transport)
            : $transport;
    }
}
