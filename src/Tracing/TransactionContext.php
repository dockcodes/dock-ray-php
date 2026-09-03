<?php

declare(strict_types=1);

namespace Dock\Ray\Tracing;

final class TransactionContext extends SpanContext
{
    private const TRACEPARENT_HEADER_REGEX = '/^[ \\t]*(?<trace_id>[0-9a-f]{32})?-?(?<span_id>[0-9a-f]{16})?-?(?<sampled>[01])?[ \\t]*$/i';

    public const DEFAULT_NAME = '<unlabeled transaction>';

    /**
     * @var string
     */
    private $name;

    /**
     * @var bool|null
     */
    private $parentSampled = null;

    public function __construct(string $name = self::DEFAULT_NAME, ?bool $parentSampled = null)
    {
        $this->name = $name;
        $this->parentSampled = $parentSampled;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getParentSampled(): ?bool
    {
        return $this->parentSampled;
    }

    public function setParentSampled(?bool $parentSampled): void
    {
        $this->parentSampled = $parentSampled;
    }

    public static function fromRayTrace(string $header)
    {
        $context = new self();

        if (!preg_match(self::TRACEPARENT_HEADER_REGEX, $header, $matches)) {
            return $context;
        }

        if (!empty($matches['trace_id'])) {
            $context->traceId = new TraceId($matches['trace_id']);
        }

        if (!empty($matches['span_id'])) {
            $context->parentSpanId = new SpanId($matches['span_id']);
        }

        if (isset($matches['sampled'])) {
            $context->parentSampled = '1' === $matches['sampled'];
        }

        return $context;
    }
}
