<?php

declare(strict_types=1);

namespace Dock\Ray\Context;

final class OsContext
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string|null
     */
    private $version = null;

    /**
     * @var string|null
     */
    private $build = null;

    /**
     * @var string|null
     */
    private $kernelVersion = null;

    public function __construct(string $name, ?string $version = null, ?string $build = null, ?string $kernelVersion = null)
    {
        if ('' === trim($name)) {
            throw new \InvalidArgumentException('The $name argument cannot be an empty string.');
        }

        $this->name = $name;
        $this->version = $version;
        $this->build = $build;
        $this->kernelVersion = $kernelVersion;
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

    public function getBuild(): ?string
    {
        return $this->build;
    }

    public function setBuild(?string $build): void
    {
        $this->build = $build;
    }

    public function getKernelVersion(): ?string
    {
        return $this->kernelVersion;
    }

    public function setKernelVersion(?string $kernelVersion): void
    {
        $this->kernelVersion = $kernelVersion;
    }
}
