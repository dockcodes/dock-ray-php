<?php

declare(strict_types=1);

namespace Dock\Ray\Tracing;

use Dock\Ray\Event;
use Dock\Ray\EventId;
use Dock\Ray\RaySdk;
use Dock\Ray\State\HubInterface;

final class Transaction extends Span
{
    /**
     * @var HubInterface
     */
    private $hub;

    /**
     * @var string
     */
    private $name;

    public function __construct(TransactionContext $context, ?HubInterface $hub = null)
    {
        parent::__construct($context);

        $this->hub = $hub ?? RaySdk::getCurrentHub();
        $this->name = $context->getName();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function initSpanRecorder(int $maxSpans = 1000): void
    {
        if (null === $this->spanRecorder) {
            $this->spanRecorder = new SpanRecorder($maxSpans);
        }

        $this->spanRecorder->add($this);
    }

    public function finish(?float $endTimestamp = null): ?EventId
    {
        if (null !== $this->endTimestamp) {
            // Transaction was already finished once and we don't want to re-flush it
            return null;
        }

        parent::finish($endTimestamp);

        if (true !== $this->sampled) {
            return null;
        }

        $finishedSpans = [];

        if (null !== $this->spanRecorder) {
            foreach ($this->spanRecorder->getSpans() as $span) {
                if ($span->getSpanId() !== $this->getSpanId() && null !== $span->getEndTimestamp()) {
                    $finishedSpans[] = $span;
                }
            }
        }

        $event = Event::createTransaction();
        $event->setSpans($finishedSpans);
        $event->setStartTimestamp($this->startTimestamp);
        $event->setTimestamp($this->endTimestamp);
        $event->setTags($this->tags);
        $event->setTransaction($this->name);
        $event->setContext('trace', $this->getTraceContext());

        return $this->hub->captureEvent($event);
    }
}
