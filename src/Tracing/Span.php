<?php

declare(strict_types=1);

namespace Dock\Ray\Tracing;

use Dock\Ray\EventId;

class Span
{
    /**
     * @var SpanId
     */
    private $spanId;

    /**
     * @var TraceId
     */
    private $traceId;

    /**
     * @var string|null
     */
    private $description = null;

    /**
     * @var string|null
     */
    private $op = null;

    /**
     * @var SpanStatus|null
     */
    private $status;

    /**
     * @var SpanId|null
     */
    private $parentSpanId;

    /**
     * @var bool|null
     */
    protected $sampled = null;

    /**
     * @var array
     */
    protected $tags = [];

    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var float
     */
    protected $startTimestamp;

    /**
     * @var float|null
     */
    protected $endTimestamp = null;

    /**
     * @var SpanRecorder|null
     */
    protected $spanRecorder = null;

    public function __construct(?SpanContext $context = null)
    {
        if (null === $context) {
            $this->traceId = TraceId::generate();
            $this->spanId = SpanId::generate();
            $this->startTimestamp = microtime(true);

            return;
        }

        $this->traceId = $context->getTraceId() ?? TraceId::generate();
        $this->spanId = $context->getSpanId() ?? SpanId::generate();
        $this->startTimestamp = $context->getStartTimestamp() ?? microtime(true);
        $this->parentSpanId = $context->getParentSpanId();
        $this->description = $context->getDescription();
        $this->op = $context->getOp();
        $this->status = $context->getStatus();
        $this->sampled = $context->getSampled();
        $this->tags = $context->getTags();
        $this->data = $context->getData();
        $this->endTimestamp = $context->getEndTimestamp();
    }

    public function setSpanId(SpanId $spanId): void
    {
        $this->spanId = $spanId;
    }

    public function getTraceId(): TraceId
    {
        return $this->traceId;
    }

    public function setTraceId(TraceId $traceId): void
    {
        $this->traceId = $traceId;
    }

    public function getParentSpanId(): ?SpanId
    {
        return $this->parentSpanId;
    }

    public function setParentSpanId(?SpanId $parentSpanId): void
    {
        $this->parentSpanId = $parentSpanId;
    }

    public function getStartTimestamp(): float
    {
        return $this->startTimestamp;
    }

    public function setStartTimestamp(float $startTimestamp): void
    {
        $this->startTimestamp = $startTimestamp;
    }

    public function getEndTimestamp(): ?float
    {
        return $this->endTimestamp;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getOp(): ?string
    {
        return $this->op;
    }

    public function setOp(?string $op): void
    {
        $this->op = $op;
    }

    public function getStatus(): ?SpanStatus
    {
        return $this->status;
    }

    public function setStatus(?SpanStatus $status): void
    {
        $this->status = $status;
    }

    public function setHttpStatus(int $statusCode): void
    {
        $this->tags['http.status_code'] = (string)$statusCode;

        $status = SpanStatus::createFromHttpStatusCode($statusCode);

        if ($status !== SpanStatus::unknownError()) {
            $this->status = $status;
        }
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): void
    {
        $this->tags = array_merge($this->tags, $tags);
    }

    public function getSpanId(): SpanId
    {
        return $this->spanId;
    }

    public function getSampled(): ?bool
    {
        return $this->sampled;
    }

    public function setSampled(?bool $sampled): void
    {
        $this->sampled = $sampled;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = array_merge($this->data, $data);
    }

    public function getTraceContext(): array
    {
        $result = [
            'span_id' => (string)$this->spanId,
            'trace_id' => (string)$this->traceId,
        ];

        if (null !== $this->parentSpanId) {
            $result['parent_span_id'] = (string)$this->parentSpanId;
        }

        if (null !== $this->description) {
            $result['description'] = $this->description;
        }

        if (null !== $this->op) {
            $result['op'] = $this->op;
        }

        if (null !== $this->status) {
            $result['status'] = (string)$this->status;
        }

        if (!empty($this->data)) {
            $result['data'] = $this->data;
        }

        if (!empty($this->tags)) {
            $result['tags'] = $this->tags;
        }

        return $result;
    }

    public function finish(?float $endTimestamp = null): ?EventId
    {
        $this->endTimestamp = $endTimestamp ?? microtime(true);

        return null;
    }

    public function startChild(SpanContext $context): self
    {
        $context = clone $context;
        $context->setSampled($this->sampled);
        $context->setParentSpanId($this->spanId);
        $context->setTraceId($this->traceId);

        $span = new self($context);
        $span->spanRecorder = $this->spanRecorder;

        if (null != $span->spanRecorder) {
            $span->spanRecorder->add($span);
        }

        return $span;
    }

    public function getSpanRecorder(): ?SpanRecorder
    {
        return $this->spanRecorder;
    }

    public function detachSpanRecorder(): void
    {
        $this->spanRecorder = null;
    }

    public function toTraceparent(): string
    {
        $sampled = '';

        if (null !== $this->sampled) {
            $sampled = $this->sampled ? '-1' : '-0';
        }

        return sprintf('%s-%s%s', (string)$this->traceId, (string)$this->spanId, $sampled);
    }
}
