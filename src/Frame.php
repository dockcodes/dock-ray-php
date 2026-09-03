<?php

declare(strict_types=1);

namespace Dock\Ray;

final class Frame
{
    public const INTERNAL_FRAME_FILENAME = '[internal]';

    public const ANONYMOUS_CLASS_PREFIX = "class@anonymous\x00";

    /**
     * @var string|null
     */
    private $functionName = null;

    /**
     * @var string|null
     */
    private $rawFunctionName = null;

    /**
     * @var string
     */
    private $file;

    /**
     * @var string|null
     */
    private $absoluteFilePath = null;

    /**
     * @var int
     */
    private $line;

    /**
     * @var array
     */
    private $preContext = [];

    /**
     * @var string|null
     */
    private $contextLine = null;

    /**
     * @var array
     */
    private $postContext = [];

    /**
     * @var bool
     */
    private $inApp;

    /**
     * @var array
     */
    private $vars = [];

    public function __construct(?string $functionName, string $file, int $line, ?string $rawFunctionName = null, ?string $absoluteFilePath = null, array $vars = [], bool $inApp = true)
    {
        $this->functionName = $functionName;
        $this->file = $file;
        $this->line = $line;
        $this->rawFunctionName = $rawFunctionName;
        $this->absoluteFilePath = $absoluteFilePath;
        $this->vars = $vars;
        $this->inApp = $inApp;
    }

    public function getFunctionName(): ?string
    {
        return $this->functionName;
    }

    public function getRawFunctionName(): ?string
    {
        return $this->rawFunctionName;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getAbsoluteFilePath(): ?string
    {
        return $this->absoluteFilePath;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getPreContext(): array
    {
        return $this->preContext;
    }

    public function setPreContext(array $preContext): void
    {
        $this->preContext = $preContext;
    }

    public function getContextLine(): ?string
    {
        return $this->contextLine;
    }
    
    public function setContextLine(?string $contextLine): void
    {
        $this->contextLine = $contextLine;
    }

    public function getPostContext(): array
    {
        return $this->postContext;
    }

    public function setPostContext(array $postContext): void
    {
        $this->postContext = $postContext;
    }

    public function isInApp(): bool
    {
        return $this->inApp;
    }

    public function setIsInApp(bool $inApp): void
    {
        $this->inApp = $inApp;
    }

    public function getVars(): array
    {
        return $this->vars;
    }

    public function setVars(array $vars): void
    {
        $this->vars = $vars;
    }

    public function isInternal(): bool
    {
        return self::INTERNAL_FRAME_FILENAME === $this->file;
    }
}
