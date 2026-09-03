<?php

declare(strict_types=1);

namespace Dock\Ray\State;

use Dock\Ray\Breadcrumb;
use Dock\Ray\ClientInterface;
use Dock\Ray\Event;
use Dock\Ray\EventHint;
use Dock\Ray\EventId;
use Dock\Ray\Integration\IntegrationInterface;
use Dock\Ray\RaySdk;
use Dock\Ray\Severity;
use Dock\Ray\Tracing\Span;
use Dock\Ray\Tracing\Transaction;
use Dock\Ray\Tracing\TransactionContext;

final class HubAdapter implements HubInterface
{
    /**
     * @var self|null
     */
    private static $instance;

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

    public function getClient(): ?ClientInterface
    {
        return RaySdk::getCurrentHub()->getClient();
    }

    public function getLastEventId(): ?EventId
    {
        return RaySdk::getCurrentHub()->getLastEventId();
    }

    public function pushScope(): Scope
    {
        return RaySdk::getCurrentHub()->pushScope();
    }

    public function popScope(): bool
    {
        return RaySdk::getCurrentHub()->popScope();
    }

    public function withScope(callable $callback): void
    {
        RaySdk::getCurrentHub()->withScope($callback);
    }

    public function configureScope(callable $callback): void
    {
        RaySdk::getCurrentHub()->configureScope($callback);
    }

    public function bindClient(ClientInterface $client): void
    {
        RaySdk::getCurrentHub()->bindClient($client);
    }

    public function captureMessage(string $message, ?Severity $level = null, ?EventHint $hint = null): ?EventId
    {
        return RaySdk::getCurrentHub()->captureMessage($message, $level, $hint);
    }

    public function captureException(\Throwable $exception, ?EventHint $hint = null): ?EventId
    {
        return RaySdk::getCurrentHub()->captureException($exception, $hint);
    }

    public function captureEvent(Event $event, ?EventHint $hint = null): ?EventId
    {
        return RaySdk::getCurrentHub()->captureEvent($event, $hint);
    }

    public function captureLastError(?EventHint $hint = null): ?EventId
    {
        return RaySdk::getCurrentHub()->captureLastError($hint);
    }

    public function addBreadcrumb(Breadcrumb $breadcrumb): bool
    {
        return RaySdk::getCurrentHub()->addBreadcrumb($breadcrumb);
    }

    public function getIntegration(string $className): ?IntegrationInterface
    {
        return RaySdk::getCurrentHub()->getIntegration($className);
    }

    public function startTransaction(TransactionContext $context, array $customSamplingContext = []): Transaction
    {
        return RaySdk::getCurrentHub()->startTransaction($context, $customSamplingContext);
    }

    public function getTransaction(): ?Transaction
    {
        return RaySdk::getCurrentHub()->getTransaction();
    }

    public function getSpan(): ?Span
    {
        return RaySdk::getCurrentHub()->getSpan();
    }

    public function setSpan(?Span $span): HubInterface
    {
        return RaySdk::getCurrentHub()->setSpan($span);
    }

    public function __clone()
    {
        throw new \BadMethodCallException('Cloning is forbidden.');
    }

    public function __wakeup()
    {
        throw new \BadMethodCallException('Unserializing instances of this class is forbidden.');
    }
}
