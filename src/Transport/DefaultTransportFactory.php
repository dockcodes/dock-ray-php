<?php

declare(strict_types=1);

namespace Dock\Thor\Transport;

use Dock\Thor\HttpClient\HttpClientFactoryInterface;
use Dock\Thor\Options;
use Dock\Thor\Serializer\PayloadSerializer;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

final class DefaultTransportFactory implements TransportFactoryInterface
{
    public function __construct(
        private readonly StreamFactoryInterface $streamFactory,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly HttpClientFactoryInterface $httpClientFactory,
        private readonly ?LoggerInterface $logger = null,
    ) {}

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
