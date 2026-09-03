<?php

declare(strict_types=1);

namespace Dock\Ray\Tracing;

final class SpanRecorder
{
    /**
     * @var int
     */
    private $maxSpans;

    /**
     * @var array
     */
    private $spans = [];

    public function __construct(int $maxSpans = 1000)
    {
        $this->maxSpans = $maxSpans;
    }

    public function add(Span $span): void
    {
        if (\count($this->spans) > $this->maxSpans) {
            $span->detachSpanRecorder();
        } else {
            $this->spans[] = $span;
        }
    }

    public function getSpans(): array
    {
        return $this->spans;
    }
}
