<?php

declare(strict_types=1);

namespace Dock\Ray\Serializer;

interface RepresentationSerializerInterface
{
    public function representationSerialize($value);
}
