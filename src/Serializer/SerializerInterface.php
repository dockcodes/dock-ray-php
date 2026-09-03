<?php

declare(strict_types=1);

namespace Dock\Ray\Serializer;

interface SerializerInterface
{
    public function serialize($value);
}
