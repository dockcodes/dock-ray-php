<?php

declare(strict_types=1);

namespace Dock\Ray\Transport;

use GuzzleHttp\Promise\PromiseInterface;
use Dock\Ray\Event;

interface TransportInterface
{
    public function send(Event $event): PromiseInterface;

    public function close(?int $timeout = null): PromiseInterface;
}
