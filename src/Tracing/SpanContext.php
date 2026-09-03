<?php

declare(strict_types=1);

namespace Dock\Ray\Tracing;

class SpanContext
{
    private const TRACEPARENT_HEADER_REGEX = '/^[ \\t]*(?<trace_id>[0-9a-f]{32})?-?(?<span_id>[0-9a-f]{16})?-?(?<sampled>[01])?[ \\t]*$/i';

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
    private $status = null;

    /**
     * @var SpanId|null
     */
    protected $parentSpanId = null;

    /**
     * @var bool|null
     */
    private $sampled = null;

    /**
     * @var SpanId|null
     */
    private $spanId = null;

    /**
     * @var TraceId|null
     */
    protected $traceId = null;

    /**
     * @var array
     */
    private $tags = [];

    /**
     * @var array
     */
    private $data = [];

    /**
     * @var float|null
     */
    private $startTimestamp = null;

    /**
     * @var float|null
     */
    private $endTimestamp = null;

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

    public function getParentSpanId(): ?SpanId
    {
        return $this->parentSpanId;
    }

    public function setParentSpanId(?SpanId $parentSpanId): void
    {
        $this->parentSpanId = $parentSpanId;
    }

    public function getSampled(): ?bool
    {
        return $this->sampled;
    }

    public function setSampled(?bool $sampled): void
    {
        $this->sampled = $sampled;
    }

    public function getSpanId(): ?SpanId
    {
        return $this->spanId;
    }

    public function setSpanId(?SpanId $spanId): void
    {
        $this->spanId = $spanId;
    }

    public function getTraceId(): ?TraceId
    {
        return $this->traceId;
    }

    public function setTraceId(?TraceId $traceId): void
    {
        $this->traceId = $traceId;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getStartTimestamp(): ?float
    {
        return $this->startTimestamp;
    }

    public function setStartTimestamp(?float $startTimestamp): void
    {
        $this->startTimestamp = $startTimestamp;
    }

    public function getEndTimestamp(): ?float
    {
        return $this->endTimestamp;
    }

    public function setEndTimestamp(?float $endTimestamp): void
    {
        $this->endTimestamp = $endTimestamp;
    }
}
