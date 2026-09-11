<?php

declare(strict_types=1);

namespace Dock\Ray;

final class EventType
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

    public static function default(): self
    {
        return self::getInstance('default');
    }

    public static function transaction(): self
    {
        return self::getInstance('transaction');
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
