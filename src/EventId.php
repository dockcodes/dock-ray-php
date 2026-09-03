<?php

declare(strict_types=1);

namespace Dock\Ray;

final class EventId implements \Stringable
{
    private string $value;

    public function __construct(string $value)
    {
        if (!preg_match('/^[a-f0-9]{32}$/i', $value)) {
            throw new \InvalidArgumentException('The $value argument must be a 32 characters long hexadecimal string.');
        }

        $this->value = $value;
    }

    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(16)));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
