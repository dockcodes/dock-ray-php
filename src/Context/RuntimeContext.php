<?php

declare(strict_types=1);

namespace Dock\Ray\Context;

final class RuntimeContext
{
    private ?string $name = null;
    private ?string $version = null;

    public function __construct(string $name, ?string $version = null)
    {
        if ('' === trim($name)) {
            throw new \InvalidArgumentException('The $name argument cannot be an empty string.');
        }

        $this->name = $name;
        $this->version = $version;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        if ('' === trim($name)) {
            throw new \InvalidArgumentException('The $name argument cannot be an empty string.');
        }

        $this->name = $name;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): void
    {
        $this->version = $version;
    }
}
