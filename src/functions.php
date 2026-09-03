<?php

declare(strict_types=1);

namespace Dock\Ray;

use Dock\Ray\Tracing\Transaction;
use Dock\Ray\Tracing\TransactionContext;

function init(array $options = []): void
{
    $client = ClientBuilder::create($options)->getClient();
    RaySdk::init()->bindClient($client);
}

function captureMessage(string $message, ?Severity $level = null, ?EventHint $hint = null): ?EventId
{
    return RaySdk::getCurrentHub()->captureMessage($message, $level, $hint);
}

function captureException(\Throwable $exception, ?EventHint $hint = null): ?EventId
{
    return RaySdk::getCurrentHub()->captureException($exception, $hint);
}

function captureEvent(Event $event, ?EventHint $hint = null): ?EventId
{
    return RaySdk::getCurrentHub()->captureEvent($event, $hint);
}

function captureLastError(?EventHint $hint = null): ?EventId
{
    return RaySdk::getCurrentHub()->captureLastError($hint);
}

function addBreadcrumb(Breadcrumb $breadcrumb): void
{
    RaySdk::getCurrentHub()->addBreadcrumb($breadcrumb);
}

function configureScope(callable $callback): void
{
    RaySdk::getCurrentHub()->configureScope($callback);
}

function withScope(callable $callback): void
{
    RaySdk::getCurrentHub()->withScope($callback);
}

function startTransaction(TransactionContext $context, array $customSamplingContext = []): Transaction
{
    return RaySdk::getCurrentHub()->startTransaction($context, $customSamplingContext);
}
