<?php

declare(strict_types=1);

namespace Dock\Ray;

use Dock\Ray\State\Hub;
use Dock\Ray\State\HubInterface;

final class RaySdk
{
    /**
     * @var HubInterface|null
     */
    private static $currentHub;

    private function __construct()
    {
    }

    public static function init(): HubInterface
    {
        self::$currentHub = new Hub();

        return self::$currentHub;
    }

    public static function getCurrentHub(): HubInterface
    {
        if (null === self::$currentHub) {
            self::$currentHub = new Hub();
        }

        return self::$currentHub;
    }

    public static function setCurrentHub(HubInterface $hub): HubInterface
    {
        self::$currentHub = $hub;

        return $hub;
    }
}
