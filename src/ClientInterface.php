<?php

declare(strict_types=1);

namespace Dock\Ray;

use GuzzleHttp\Promise\PromiseInterface;
use Dock\Ray\Integration\IntegrationInterface;
use Dock\Ray\State\Scope;

interface ClientInterface
{
    public function getOptions(): Options;

    public function captureMessage(string $message, ?Severity $level = null, ?Scope $scope = null): ?EventId;

    public function captureException(\Throwable $exception, ?Scope $scope = null): ?EventId;

    public function captureLastError(?Scope $scope = null): ?EventId;

    public function captureEvent(Event $event, ?EventHint $hint = null, ?Scope $scope = null): ?EventId;
    
    public function getIntegration(string $className): ?IntegrationInterface;

    public function flush(?int $timeout = null): PromiseInterface;
}
