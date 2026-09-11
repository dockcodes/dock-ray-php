<?php

declare(strict_types=1);

namespace Dock\Ray\Framework;

use Dock\Ray\State\HubInterface;
use Dock\Ray\RaySdk;
use Dock\Ray\Tracing\Span;
use Dock\Ray\Tracing\SpanContext;
use Dock\Ray\Tracing\Transaction;
use Dock\Ray\Tracing\TransactionContext;

/**
 * Transakcja jednego żądania HTTP.
 *
 * Panel czyta z transakcji `contexts.trace.data.url`, `contexts.trace.data.method`
 * i `tags['http.status_code']` — bez kompletu tych trzech pól ingest odrzuca
 * zdarzenie. Każdy mostek frameworkowy składałby je sam, więc kształt zapisany
 * jest tutaj raz: mostek podaje nazwę, adres i metodę, a na końcu status.
 */
final class HttpTransaction
{
    private ?Transaction $transaction = null;

    private ?Span $child = null;

    private HubInterface $hub;

    private function __construct(HubInterface $hub)
    {
        $this->hub = $hub;
    }

    public static function start(
        string $name,
        string $url,
        string $method,
        ?float $startTimestamp = null,
        ?HubInterface $hub = null
    ): self {
        $instance = new self($hub ?? RaySdk::getCurrentHub());

        if (! $instance->isTracing()) {
            return $instance;
        }

        $context = new TransactionContext($name);
        $context->setOp('http.server');
        $context->setData(['url' => $url, 'method' => strtoupper($method)]);
        $context->setStartTimestamp($startTimestamp ?? microtime(true));

        $transaction = $instance->hub->startTransaction($context);

        if ($transaction->getSampled() !== true) {
            return $instance;
        }

        $instance->transaction = $transaction;
        $instance->hub->setSpan($transaction);

        return $instance;
    }

    /**
     * Dokłada podspan mierzący wycinek żądania — bootstrap frameworka,
     * zapytania do bazy, renderowanie widoku.
     */
    public function child(string $op, ?float $startTimestamp = null, ?float $endTimestamp = null): ?Span
    {
        if ($this->transaction === null) {
            return null;
        }

        $context = new SpanContext();
        $context->setOp($op);
        $context->setStartTimestamp($startTimestamp ?? microtime(true));

        if ($endTimestamp !== null) {
            $context->setEndTimestamp($endTimestamp);
        }

        return $this->transaction->startChild($context);
    }

    /**
     * Otwiera span obejmujący obsługę żądania przez framework; zamyka go
     * `finish()`.
     */
    public function measureHandling(string $op, ?float $startTimestamp = null): void
    {
        $this->child = $this->child($op, $startTimestamp);

        if ($this->child !== null) {
            $this->hub->setSpan($this->child);
        }
    }

    public function finish(int $statusCode, ?float $endTimestamp = null): void
    {
        if ($this->transaction === null) {
            return;
        }

        if ($this->child !== null) {
            $this->child->finish($endTimestamp);
        }

        $this->hub->setSpan($this->transaction);

        $this->transaction->setHttpStatus($statusCode);
        $this->transaction->finish($endTimestamp);

        $this->transaction = null;
        $this->child = null;
    }

    public function isSampled(): bool
    {
        return $this->transaction !== null;
    }

    private function isTracing(): bool
    {
        $client = $this->hub->getClient();

        return null !== $client && $client->getOptions()->isTracingEnabled();
    }
}
