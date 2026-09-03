<?php

declare(strict_types=1);

namespace Dock\Ray\Integration;

use Composer\InstalledVersions;
use Jean85\PrettyVersions;
use PackageVersions\Versions;
use Dock\Ray\Event;
use Dock\Ray\RaySdk;
use Dock\Ray\State\Scope;

final class ModulesIntegration implements IntegrationInterface
{
    private static $packages = [];

    public function setupOnce(): void
    {
        Scope::addGlobalEventProcessor(static function (Event $event): Event {
            $integration = RaySdk::getCurrentHub()->getIntegration(self::class);

            // The integration could be bound to a client that is not the one
            // attached to the current hub. If this is the case, bail out
            if (null !== $integration) {
                $event->setModules(self::getComposerPackages());
            }

            return $event;
        });
    }

    private static function getComposerPackages(): array
    {
        if (empty(self::$packages)) {
            foreach (self::getInstalledPackages() as $package) {
                try {
                    self::$packages[$package] = PrettyVersions::getVersion($package)->getPrettyVersion();
                } catch (\Throwable $exception) {
                    continue;
                }
            }
        }

        return self::$packages;
    }

    private static function getInstalledPackages(): array
    {
        if (class_exists(InstalledVersions::class)) {
            return InstalledVersions::getInstalledPackages();
        }

        if (class_exists(Versions::class)) {
            return array_keys(Versions::VERSIONS);
        }

        return ['dockcodes/dock-ray'];
    }
}
