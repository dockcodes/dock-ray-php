<?php

declare(strict_types=1);

namespace Dock\Ray\Integration;

use Psr\Log\LoggerInterface;
use Dock\Ray\Options;

final class IntegrationRegistry
{
    private static $instance;

    private $integrations = [];

    private function __construct()
    {
    }
    
    public static function getInstance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }
    
    public function setupIntegrations(Options $options, LoggerInterface $logger): array
    {
        $integrations = [];

        foreach ($this->getIntegrationsToSetup($options) as $integration) {
            $integrations[\get_class($integration)] = $integration;

            $this->setupIntegration($integration, $logger);
        }

        return $integrations;
    }

    private function setupIntegration(IntegrationInterface $integration, LoggerInterface $logger): void
    {
        $integrationName = \get_class($integration);

        if (isset($this->integrations[$integrationName])) {
            return;
        }

        $integration->setupOnce();

        $this->integrations[$integrationName] = true;

        $logger->debug(sprintf('The "%s" integration has been installed.', $integrationName));
    }

    private function getIntegrationsToSetup(Options $options): array
    {
        $integrations = [];
        $defaultIntegrations = $this->getDefaultIntegrations($options);
        $userIntegrations = $options->getIntegrations();

        if (\is_array($userIntegrations)) {
            $userIntegrationsClasses = array_map('get_class', $userIntegrations);
            $pickedIntegrationsClasses = [];

            foreach ($defaultIntegrations as $defaultIntegration) {
                $integrationClassName = \get_class($defaultIntegration);

                if (!\in_array($integrationClassName, $userIntegrationsClasses, true) && !isset($pickedIntegrationsClasses[$integrationClassName])) {
                    $integrations[] = $defaultIntegration;
                    $pickedIntegrationsClasses[$integrationClassName] = true;
                }
            }

            foreach ($userIntegrations as $userIntegration) {
                $integrationClassName = \get_class($userIntegration);

                if (!isset($pickedIntegrationsClasses[$integrationClassName])) {
                    $integrations[] = $userIntegration;
                    $pickedIntegrationsClasses[$integrationClassName] = true;
                }
            }
        } else {
            $integrations = $userIntegrations($defaultIntegrations);

            if (!\is_array($integrations)) {
                throw new \UnexpectedValueException(sprintf('Expected the callback set for the "integrations" option to return a list of integrations. Got: "%s".', get_debug_type($integrations)));
            }
        }

        return $integrations;
    }

    private function getDefaultIntegrations(Options $options): array
    {
        if (!$options->hasDefaultIntegrations()) {
            return [];
        }

        return [
            new ExceptionListenerIntegration(),
            new ErrorListenerIntegration(),
            new FatalErrorListenerIntegration(),
            new RequestIntegration(),
            new TransactionIntegration(),
            new FrameContextifierIntegration(),
            new EnvironmentIntegration(),
        ];
    }
}
