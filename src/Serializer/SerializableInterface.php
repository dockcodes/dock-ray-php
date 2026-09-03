<?php

declare(strict_types=1);

namespace Dock\Ray\Serializer;

interface SerializableInterface
{
    public function serialize(): ?array;
}
