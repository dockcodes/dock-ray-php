<?php

declare(strict_types=1);

namespace Dock\Ray\Integration;

interface IntegrationInterface
{
    public function setupOnce(): void;
}
