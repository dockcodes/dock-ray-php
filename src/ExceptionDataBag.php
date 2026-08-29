<?php

declare(strict_types=1);

namespace Dock\Thor;

final class ExceptionDataBag
{
    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $value;

    /**
     * @var Stacktrace|null
     */
    private $stacktrace;

    /**
     * @var ExceptionMechanism|null
     */
    private $mechanism;

    public function __construct(\Throwable $exception, ?Stacktrace $stacktrace = null, ?ExceptionMechanism $mechanism = null)
    {
        $this->type = \get_class($exception);
        $this->value = $exception->getMessage();
        $this->stacktrace = $stacktrace;
        $this->mechanism = $mechanism;
    }

    /**
     * Błąd, który nie jest wyjątkiem PHP — zgłoszenie z przeglądarki albo
     * z innego środowiska uruchomieniowego. Typ jest wtedy nazwą z tamtej
     * strony (`TypeError`, `ReferenceError`), a nie klasą PHP.
     */
    public static function create(string $type, string $value, ?Stacktrace $stacktrace = null, ?ExceptionMechanism $mechanism = null): self
    {
        $instance = new self(new \RuntimeException($value), $stacktrace, $mechanism);
        $instance->type = $type;

        return $instance;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function getStacktrace(): ?Stacktrace
    {
        return $this->stacktrace;
    }

    public function setStacktrace(Stacktrace $stacktrace): void
    {
        $this->stacktrace = $stacktrace;
    }

    public function getMechanism(): ?ExceptionMechanism
    {
        return $this->mechanism;
    }

    public function setMechanism(?ExceptionMechanism $mechanism): void
    {
        $this->mechanism = $mechanism;
    }
}
