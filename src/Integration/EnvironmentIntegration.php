<?php

declare(strict_types=1);

namespace Dock\Ray\Integration;

use Dock\Ray\Context\OsContext;
use Dock\Ray\Context\RuntimeContext;
use Dock\Ray\Event;
use Dock\Ray\RaySdk;
use Dock\Ray\State\Scope;
use Dock\Ray\Util\PHPVersion;

final class EnvironmentIntegration implements IntegrationInterface
{
    public function setupOnce(): void
    {
        Scope::addGlobalEventProcessor(static function (Event $event): Event {
            $integration = RaySdk::getCurrentHub()->getIntegration(self::class);

            if (null !== $integration) {
                $event->setRuntimeContext($integration->updateRuntimeContext($event->getRuntimeContext()));
                $event->setOsContext($integration->updateServerOsContext($event->getOsContext()));
            }

            return $event;
        });
    }

    private function updateRuntimeContext(?RuntimeContext $runtimeContext): RuntimeContext
    {
        if (null === $runtimeContext) {
            $runtimeContext = new RuntimeContext('php');
        }

        if (null === $runtimeContext->getVersion()) {
            $runtimeContext->setVersion(PHPVersion::parseVersion());
        }

        return $runtimeContext;
    }

    private function updateServerOsContext(?OsContext $osContext): ?OsContext
    {
        if (!\function_exists('php_uname')) {
            return $osContext;
        }

        if (null === $osContext) {
            $osContext = new OsContext(php_uname('s'));
        }

        if (null === $osContext->getVersion()) {
            $osContext->setVersion(php_uname('r'));
        }

        if (null === $osContext->getBuild()) {
            $osContext->setBuild(php_uname('v'));
        }

        if (null === $osContext->getKernelVersion()) {
            $osContext->setKernelVersion(php_uname('a'));
        }

        return $osContext;
    }
}
