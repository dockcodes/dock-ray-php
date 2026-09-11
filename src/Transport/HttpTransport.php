<?php

declare(strict_types=1);

namespace Dock\Ray\Transport;

use Dock\Ray\Event;
use Dock\Ray\EventType;
use Dock\Ray\Options;
use Dock\Ray\Response;
use Dock\Ray\ResponseStatus;
use Dock\Ray\Serializer\PayloadSerializerInterface;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Http\Client\HttpAsyncClient as HttpAsyncClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class HttpTransport implements TransportInterface
{
    private LoggerInterface $logger;

    private Options $options;

    private HttpAsyncClientInterface $httpClient;

    private StreamFactoryInterface $streamFactory;

    private RequestFactoryInterface $requestFactory;

    private PayloadSerializerInterface $payloadSerializer;

    public function __construct(
        Options $options,
        HttpAsyncClientInterface $httpClient,
        StreamFactoryInterface $streamFactory,
        RequestFactoryInterface $requestFactory,
        PayloadSerializerInterface $payloadSerializer,
        ?LoggerInterface $logger = null
    ) {
        $this->options = $options;
        $this->httpClient = $httpClient;
        $this->streamFactory = $streamFactory;
        $this->requestFactory = $requestFactory;
        $this->payloadSerializer = $payloadSerializer;
        $this->logger = $logger ?? new NullLogger();
    }

    public function send(Event $event): PromiseInterface
    {
        $authData = $this->options->getAuthData();

        if (null === $authData) {
            return new FulfilledPromise(new Response(ResponseStatus::skipped(), $event));
        }

        $endpoint = EventType::transaction() === $event->getType()
            ? $authData->getTransactionApiEndpointUrl()
            : $authData->getProjectApiEndpointUrl();

        $request = $this->requestFactory->createRequest('POST', $endpoint)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream($this->payloadSerializer->serialize($event)));

        try {
            /** @var ResponseInterface $response */
            $response = $this->httpClient->sendAsyncRequest($request)->wait();
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('Failed to send the event to DockRay. Reason: "%s".', $exception->getMessage()),
                ['exception' => $exception, 'event' => $event]
            );

            return new RejectedPromise(new Response(ResponseStatus::failed(), $event));
        }

        $sendResponse = new Response(ResponseStatus::createFromHttpStatusCode($response->getStatusCode()), $event);

        if (ResponseStatus::success() === $sendResponse->getStatus()) {
            return new FulfilledPromise($sendResponse);
        }

        return new RejectedPromise($sendResponse);
    }

    public function close(?int $timeout = null): PromiseInterface
    {
        return new FulfilledPromise(true);
    }
}
