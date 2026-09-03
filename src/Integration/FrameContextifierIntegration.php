<?php

declare(strict_types=1);

namespace Dock\Ray\Integration;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Dock\Ray\Event;
use Dock\Ray\RaySdk;
use Dock\Ray\Stacktrace;
use Dock\Ray\State\Scope;

final class FrameContextifierIntegration implements IntegrationInterface
{
    private $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }
    
    public function setupOnce(): void
    {
        Scope::addGlobalEventProcessor(static function (Event $event): Event {
            $client = RaySdk::getCurrentHub()->getClient();

            if (null === $client) {
                return $event;
            }

            $maxContextLines = $client->getOptions()->getContextLines();
            $integration = $client->getIntegration(self::class);

            if (null === $integration || null === $maxContextLines) {
                return $event;
            }

            $stacktrace = $event->getStacktrace();

            if (null !== $stacktrace) {
                $integration->addContextToStacktraceFrames($maxContextLines, $stacktrace);
            }

            foreach ($event->getExceptions() as $exception) {
                if (is_object($exception) && null !== $exception->getStacktrace()) {
                    $integration->addContextToStacktraceFrames($maxContextLines, $exception->getStacktrace());
                }
            }

            return $event;
        });
    }

    
    private function addContextToStacktraceFrames(int $maxContextLines, Stacktrace $stacktrace): void
    {
        foreach ($stacktrace->getFrames() as $frame) {
            if ($frame->isInternal() || null === $frame->getAbsoluteFilePath()) {
                continue;
            }

            $sourceCodeExcerpt = $this->getSourceCodeExcerpt($maxContextLines, $frame->getAbsoluteFilePath(), $frame->getLine());

            $frame->setPreContext($sourceCodeExcerpt['pre_context']);
            $frame->setContextLine($sourceCodeExcerpt['context_line']);
            $frame->setPostContext($sourceCodeExcerpt['post_context']);
        }
    }

    private function getSourceCodeExcerpt(int $maxContextLines, string $filePath, int $lineNumber): array
    {
        $frame = [
            'pre_context' => [],
            'context_line' => null,
            'post_context' => [],
        ];

        $target = max(0, ($lineNumber - ($maxContextLines + 1)));
        $currentLineNumber = $target + 1;

        try {
            $file = new \SplFileObject($filePath);
            $file->seek($target);

            while (!$file->eof()) {
                /** @var string $line */
                $line = $file->current();
                $line = rtrim($line, "\r\n");

                if ($currentLineNumber === $lineNumber) {
                    $frame['context_line'] = $line;
                } elseif ($currentLineNumber < $lineNumber) {
                    $frame['pre_context'][] = $line;
                } elseif ($currentLineNumber > $lineNumber) {
                    $frame['post_context'][] = $line;
                }

                ++$currentLineNumber;

                if ($currentLineNumber > $lineNumber + $maxContextLines) {
                    break;
                }

                $file->next();
            }
        } catch (\Throwable $exception) {
            $this->logger->warning(sprintf('Failed to get the source code excerpt for the file "%s".', $filePath));
        }

        return $frame;
    }
}
