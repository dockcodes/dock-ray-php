<?php

declare(strict_types=1);

namespace Dock\Thor\Serializer;

use Dock\Thor\Event;

interface PayloadSerializerInterface
{
    public function serialize(Event $event): string;
}
