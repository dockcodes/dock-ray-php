<?php

declare(strict_types=1);

namespace Dock\Ray\Serializer;

use Dock\Ray\Event;

interface PayloadSerializerInterface
{
    public function serialize(Event $event): string;
}
