<?php

declare(strict_types=1);

namespace Dock\Ray\Monolog;

use Monolog\Logger;
use Monolog\LogRecord;

/*
 * Godzi trzy niezgodne ze sobą wersje Monologa.
 *
 * `AbstractProcessingHandler::write()` przyjmuje w Monologu 1 i 2 tablicę,
 * a w 3 obiekt `LogRecord` — sygnatury nie da się napisać raz dla obu, bo
 * niezgodna z rodzicem jest błędem krytycznym przy ładowaniu klasy. Dlatego
 * trait jest deklarowany warunkowo, a handler implementuje `doWrite(array)`,
 * czyli kształt wspólny dla wszystkich wersji.
 *
 */
if (Logger::API >= 3) {
    trait CompatibilityProcessingHandlerTrait
    {
        abstract protected function doWrite(array $record): void;

        protected function write(LogRecord $record): void
        {
            $this->doWrite($record->toArray());
        }
    }
} else {
    trait CompatibilityProcessingHandlerTrait
    {
        abstract protected function doWrite(array $record): void;

        protected function write(array $record): void
        {
            $this->doWrite($record);
        }
    }
}
