<?php

declare(strict_types=1);

namespace Dock\Ray\Integration;

use Dock\Ray\ErrorHandler;
use Dock\Ray\Exception\FatalErrorException;
use Dock\Ray\RaySdk;

final class FatalErrorListenerIntegration extends AbstractErrorListenerIntegration
{
    public function setupOnce(): void
    {
        $errorHandler = ErrorHandler::registerOnceFatalErrorHandler();
        $errorHandler->addFatalErrorHandlerListener(static function (FatalErrorException $exception): void {
            $currentHub = RaySdk::getCurrentHub();
            $integration = $currentHub->getIntegration(self::class);
            $client = $currentHub->getClient();

            if (null === $integration || null === $client) {
                return;
            }

            if (!($client->getOptions()->getErrorTypes() & $exception->getSeverity())) {
                return;
            }

            $integration->captureException($currentHub, $exception);
        });
    }
}
