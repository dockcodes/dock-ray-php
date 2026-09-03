<?php

declare(strict_types=1);

namespace Dock\Ray;

final class ResponseStatus implements \Stringable
{
    /**
     * @var string
     */
    private $value;

    /**
     * @var array
     */
    private static $instances = [];

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function unknown(): self
    {
        return self::getInstance('UNKNOWN');
    }

    public static function skipped(): self
    {
        return self::getInstance('SKIPPED');
    }

    public static function success(): self
    {
        return self::getInstance('SUCCESS');
    }

    public static function rateLimit(): self
    {
        return self::getInstance('RATE_LIMIT');
    }

    public static function invalid(): self
    {
        return self::getInstance('INVALID');
    }

    public static function failed(): self
    {
        return self::getInstance('FAILED');
    }

    public static function createFromHttpStatusCode(int $statusCode): self
    {
        switch (true) {
            case $statusCode >= 200 && $statusCode < 300:
                return self::success();
            case 429 === $statusCode:
                return self::rateLimit();
            case $statusCode >= 400 && $statusCode < 500:
                return self::invalid();
            case $statusCode >= 500:
                return self::failed();
            default:
                return self::unknown();
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function getInstance(string $value): self
    {
        if (!isset(self::$instances[$value])) {
            self::$instances[$value] = new self($value);
        }

        return self::$instances[$value];
    }
}
