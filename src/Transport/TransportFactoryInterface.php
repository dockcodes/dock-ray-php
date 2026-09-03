<?php

declare(strict_types=1);

namespace Dock\Ray\Transport;

use Dock\Ray\Options;

interface TransportFactoryInterface
{
    public function create(Options $options): TransportInterface;
}
