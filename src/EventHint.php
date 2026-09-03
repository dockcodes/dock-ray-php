<?php

declare(strict_types=1);

namespace Dock\Ray;

final class EventHint
{
    /**
     * @var \Throwable|null
     */
    public $exception = null;

    /**
     * @var Stacktrace|null
     */
    public $stacktrace = null;

    /**
     * @var array
     */
    public $extra = [];
    
    public static function fromArray(array $hintData): self
    {
        $hint = new self();
        $exception = $hintData['exception'] ?? null;
        $stacktrace = $hintData['stacktrace'] ?? null;
        $extra = $hintData['extra'] ?? [];

        if (null !== $exception && !$exception instanceof \Throwable) {
            throw new \InvalidArgumentException(sprintf('The value of the "exception" field must be an instance of a class implementing the "%s" interface. Got: "%s".', \Throwable::class, get_debug_type($exception)));
        }

        if (null !== $stacktrace && !$stacktrace instanceof Stacktrace) {
            throw new \InvalidArgumentException(sprintf('The value of the "stacktrace" field must be an instance of the "%s" class. Got: "%s".', Stacktrace::class, get_debug_type($stacktrace)));
        }

        if (!\is_array($extra)) {
            throw new \InvalidArgumentException(sprintf('The value of the "extra" field must be an array. Got: "%s".', get_debug_type($extra)));
        }

        $hint->exception = $exception;
        $hint->stacktrace = $stacktrace;
        $hint->extra = $extra;

        return $hint;
    }
}
