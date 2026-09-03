<?php

declare(strict_types=1);

namespace Dock\Ray\Integration;

use Dock\Ray\ErrorHandler;
use Dock\Ray\Exception\SilencedErrorException;
use Dock\Ray\RaySdk;

final class ErrorListenerIntegration extends AbstractErrorListenerIntegration
{
    public function setupOnce(): void
    {
        $errorHandler = ErrorHandler::registerOnceErrorHandler();
        $errorHandler->addErrorHandlerListener(static function (\ErrorException $exception): void {
            $currentHub = RaySdk::getCurrentHub();
            $integration = $currentHub->getIntegration(self::class);
            $client = $currentHub->getClient();

            if (null === $integration || null === $client) {
                return;
            }

            if ($exception instanceof SilencedErrorException && !$client->getOptions()->shouldCaptureSilencedErrors()) {
                return;
            }

            if (!$exception instanceof SilencedErrorException && !($client->getOptions()->getErrorTypes() & $exception->getSeverity())) {
                return;
            }

            $integration->captureException($currentHub, $exception);
        });
    }
}
