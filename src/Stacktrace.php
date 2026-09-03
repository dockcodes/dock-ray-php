<?php

declare(strict_types=1);

namespace Dock\Ray;

final class Stacktrace
{
    /**
     * @var array
     */
    private $frames = [];

    public function __construct(array $frames)
    {
        if (empty($frames)) {
            throw new \InvalidArgumentException('Expected a non empty list of frames.');
        }

        foreach ($frames as $frame) {
            if (!$frame instanceof Frame) {
                throw new \UnexpectedValueException(sprintf('Expected an instance of the "%s" class. Got: "%s".', Frame::class, get_debug_type($frame)));
            }
        }

        $this->frames = $frames;
    }

    public function getFrames(): array
    {
        return $this->frames;
    }

    public function getFrame(int $index): Frame
    {
        if ($index < 0 || $index >= \count($this->frames)) {
            throw new \OutOfBoundsException();
        }

        return $this->frames[$index];
    }

    public function addFrame(Frame $frame): void
    {
        array_unshift($this->frames, $frame);
    }

    public function removeFrame(int $index): void
    {
        if (!isset($this->frames[$index])) {
            throw new \OutOfBoundsException(sprintf('Cannot remove the frame at index %d.', $index));
        }

        if (1 === \count($this->frames)) {
            throw new \RuntimeException('Cannot remove all frames from the stacktrace.');
        }

        array_splice($this->frames, $index, 1);
    }
}
