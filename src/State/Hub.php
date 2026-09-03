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

final class Hub implements HubInterface
{
    /**
     * @var array
     */
    private $stack = [];

    /**
     * @var EventId
     */
    private $lastEventId;

    public function __construct(?ClientInterface $client = null, ?Scope $scope = null)
    {
        $this->stack[] = new Layer($client, $scope ?? new Scope());
    }

    public function getClient(): ?ClientInterface
    {
        return $this->getStackTop()->getClient();
    }

    public function getLastEventId(): ?EventId
    {
        return $this->lastEventId;
    }

    public function pushScope(): Scope
    {
        $clonedScope = clone $this->getScope();

        $this->stack[] = new Layer($this->getClient(), $clonedScope);

        return $clonedScope;
    }

    public function popScope(): bool
    {
        if (1 === \count($this->stack)) {
            return false;
        }

        return null !== array_pop($this->stack);
    }

    public function withScope(callable $callback): void
    {
        $scope = $this->pushScope();

        try {
            $callback($scope);
        } finally {
            $this->popScope();
        }
    }

    public function configureScope(callable $callback): void
    {
        $callback($this->getScope());
    }

    public function bindClient(ClientInterface $client): void
    {
        $layer = $this->getStackTop();
        $layer->setClient($client);
    }

    public function captureMessage(string $message, ?Severity $level = null, ?EventHint $hint = null): ?EventId
    {
        $client = $this->getClient();
        if (null !== $client) {
            return $this->lastEventId = $client->captureMessage($message, $level, $this->getScope(), $hint);
        }

        return null;
    }

    public function captureException(\Throwable $exception, ?EventHint $hint = null): ?EventId
    {
        $client = $this->getClient();

        if (null !== $client) {
            return $this->lastEventId = $client->captureException($exception, $this->getScope(), $hint);
        }

        return null;
    }

    public function captureEvent(Event $event, ?EventHint $hint = null): ?EventId
    {
        $client = $this->getClient();

        if (null !== $client) {
            return $this->lastEventId = $client->captureEvent($event, $hint, $this->getScope());
        }

        return null;
    }

    public function captureLastError(?EventHint $hint = null): ?EventId
    {
        $client = $this->getClient();

        if (null !== $client) {
            return $this->lastEventId = $client->captureLastError($this->getScope(), $hint);
        }

        return null;
    }

    public function addBreadcrumb(Breadcrumb $breadcrumb): bool
    {
        $client = $this->getClient();

        if (null === $client) {
            return false;
        }

        $options = $client->getOptions();
        $beforeBreadcrumbCallback = $options->getBeforeBreadcrumbCallback();
        $maxBreadcrumbs = $options->getMaxBreadcrumbs();

        if ($maxBreadcrumbs <= 0) {
            return false;
        }

        $breadcrumb = $beforeBreadcrumbCallback($breadcrumb);

        if (null !== $breadcrumb) {
            $this->getScope()->addBreadcrumb($breadcrumb, $maxBreadcrumbs);
        }

        return null !== $breadcrumb;
    }

    public function getIntegration(string $className): ?IntegrationInterface
    {
        $client = $this->getClient();

        if (null !== $client) {
            return $client->getIntegration($className);
        }

        return null;
    }

    public function startTransaction(TransactionContext $context, array $customSamplingContext = []): Transaction
    {
        $transaction = new Transaction($context, $this);
        $client = $this->getClient();
        $options = null !== $client ? $client->getOptions() : null;

        if (null === $options || !$options->isTracingEnabled()) {
            $transaction->setSampled(false);

            return $transaction;
        }

        $samplingContext = SamplingContext::getDefault($context);
        $samplingContext->setAdditionalContext($customSamplingContext);

        $tracesSampler = $options->getTracesSampler();

        if (null === $transaction->getSampled()) {
            $sampleRate = null !== $tracesSampler
                ? $tracesSampler($samplingContext)
                : $this->getSampleRate($samplingContext->getParentSampled(), $options->getTracesSampleRate());

            if (!$this->isValidSampleRate($sampleRate)) {
                $transaction->setSampled(false);

                return $transaction;
            }

            if (0.0 === $sampleRate) {
                $transaction->setSampled(false);

                return $transaction;
            }

            $transaction->setSampled(mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax() < $sampleRate);
        }

        if (!$transaction->getSampled()) {
            return $transaction;
        }

        $transaction->initSpanRecorder();

        return $transaction;
    }

    public function getTransaction(): ?Transaction
    {
        return $this->getScope()->getTransaction();
    }

    public function setSpan(?Span $span): HubInterface
    {
        $this->getScope()->setSpan($span);

        return $this;
    }

    public function getSpan(): ?Span
    {
        return $this->getScope()->getSpan();
    }

    private function getScope(): Scope
    {
        return $this->getStackTop()->getScope();
    }

    private function getStackTop(): Layer
    {
        return $this->stack[\count($this->stack) - 1];
    }

    private function getSampleRate(?bool $hasParentBeenSampled, float $fallbackSampleRate): float
    {
        if (true === $hasParentBeenSampled) {
            return 1;
        }

        if (false === $hasParentBeenSampled) {
            return 0;
        }

        return $fallbackSampleRate;
    }

    private function isValidSampleRate($sampleRate): bool
    {
        if (!\is_float($sampleRate) && !\is_int($sampleRate)) {
            return false;
        }

        if ($sampleRate < 0 || $sampleRate > 1) {
            return false;
        }

        return true;
    }
}
