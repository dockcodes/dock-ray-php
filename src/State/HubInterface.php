<?php

declare(strict_types=1);

namespace Dock\Ray\State;

use Dock\Ray\Breadcrumb;
use Dock\Ray\ClientInterface;
use Dock\Ray\Event;
use Dock\Ray\EventHint;
use Dock\Ray\EventId;
use Dock\Ray\Integration\IntegrationInterface;
use Dock\Ray\Severity;
use Dock\Ray\Tracing\SamplingContext;
use Dock\Ray\Tracing\Span;
use Dock\Ray\Tracing\Transaction;
use Dock\Ray\Tracing\TransactionContext;

interface HubInterface
{
    public function getClient(): ?ClientInterface;
    
    public function getLastEventId(): ?EventId;

    public function pushScope(): Scope;

    public function popScope(): bool;

    public function withScope(callable $callback): void;
    
    public function configureScope(callable $callback): void;

    public function bindClient(ClientInterface $client): void;

    public function captureMessage(string $message, ?Severity $level = null/*, ?EventHint $hint = null*/): ?EventId;

    public function captureException(\Throwable $exception/*, ?EventHint $hint = null*/): ?EventId;

    public function captureEvent(Event $event, ?EventHint $hint = null): ?EventId;

    public function captureLastError(/*?EventHint $hint = null*/): ?EventId;

    public function addBreadcrumb(Breadcrumb $breadcrumb): bool;

    public function getIntegration(string $className): ?IntegrationInterface;

    public function startTransaction(TransactionContext $context/*, array $customSamplingContext = []*/): Transaction;

    public function getTransaction(): ?Transaction;

    public function getSpan(): ?Span;

    public function setSpan(?Span $span): HubInterface;
}
