<?php

declare(strict_types=1);

namespace Dock\Ray;

final class ExceptionMechanism
{
    public const TYPE_GENERIC = 'generic';

    /**
     * @var string
     */
    private $type;

    /**
     * @var bool
     */
    private $handled;

    public function __construct(string $type, bool $handled)
    {
        $this->type = $type;
        $this->handled = $handled;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isHandled(): bool
    {
        return $this->handled;
    }
}
