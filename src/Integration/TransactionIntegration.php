<?php

declare(strict_types=1);

namespace Dock\Ray\Integration;

use Dock\Ray\Event;
use Dock\Ray\EventHint;
use Dock\Ray\RaySdk;
use Dock\Ray\State\Scope;

final class TransactionIntegration implements IntegrationInterface
{
    public function setupOnce(): void
    {
        Scope::addGlobalEventProcessor(static function (Event $event, EventHint $hint): Event {
            $integration = RaySdk::getCurrentHub()->getIntegration(self::class);

            if (null === $integration) {
                return $event;
            }

            if (null !== $event->getTransaction()) {
                return $event;
            }

            if (isset($hint->extra['transaction']) && \is_string($hint->extra['transaction'])) {
                $event->setTransaction($hint->extra['transaction']);
            } elseif (isset($_SERVER['PATH_INFO'])) {
                $event->setTransaction($_SERVER['PATH_INFO']);
            }

            return $event;
        });
    }
}
