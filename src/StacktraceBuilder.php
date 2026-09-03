<?php

declare(strict_types=1);

namespace Dock\Ray;

use Dock\Ray\Serializer\RepresentationSerializerInterface;

final class StacktraceBuilder
{
    /**
     * @var FrameBuilder
     */
    private $frameBuilder;

    public function __construct(Options $options, RepresentationSerializerInterface $representationSerializer)
    {
        $this->frameBuilder = new FrameBuilder($options, $representationSerializer);
    }

    public function buildFromException(\Throwable $exception): Stacktrace
    {
        return $this->buildFromBacktrace($exception->getTrace(), $exception->getFile(), $exception->getLine());
    }

    public function buildFromBacktrace(array $backtrace, string $file, int $line): Stacktrace
    {
        $frames = [];

        foreach ($backtrace as $backtraceFrame) {
            array_unshift($frames, $this->frameBuilder->buildFromBacktraceFrame($file, $line, $backtraceFrame));

            $file = $backtraceFrame['file'] ?? Frame::INTERNAL_FRAME_FILENAME;
            $line = $backtraceFrame['line'] ?? 0;
        }

        array_unshift($frames, $this->frameBuilder->buildFromBacktraceFrame($file, $line, []));

        return new Stacktrace($frames);
    }
}
