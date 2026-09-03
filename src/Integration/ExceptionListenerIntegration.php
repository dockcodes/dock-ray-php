<?php

declare(strict_types=1);

namespace Dock\Ray\Integration;

use Dock\Ray\ErrorHandler;
use Dock\Ray\RaySdk;

final class ExceptionListenerIntegration extends AbstractErrorListenerIntegration
{
    public function setupOnce(): void
    {
        $errorHandler = ErrorHandler::registerOnceExceptionHandler();
        $errorHandler->addExceptionHandlerListener(static function (\Throwable $exception): void {
            $currentHub = RaySdk::getCurrentHub();
            $integration = $currentHub->getIntegration(self::class);

            if (null === $integration) {
                return;
            }

            $integration->captureException($currentHub, $exception);
        });
    }
}
