<?php

declare(strict_types=1);

namespace Dock\Ray\Transport;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Dock\Ray\Event;
use Dock\Ray\Response;
use Dock\Ray\ResponseStatus;

final class NullTransport implements TransportInterface
{
    public function send(Event $event): PromiseInterface
    {
        return new FulfilledPromise(new Response(ResponseStatus::skipped(), $event));
    }

    public function close(?int $timeout = null): PromiseInterface
    {
        return new FulfilledPromise(true);
    }
}
