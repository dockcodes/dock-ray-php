<?php

declare(strict_types=1);

namespace Dock\Ray\Monolog;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Dock\Ray\Event;
use Dock\Ray\EventHint;
use Dock\Ray\Severity;
use Dock\Ray\State\HubInterface;
use Dock\Ray\State\Scope;

final class Handler extends AbstractProcessingHandler
{
    private $hub;

    public function __construct(HubInterface $hub, $level = Logger::DEBUG, bool $bubble = true)
    {
        $this->hub = $hub;

        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $event = Event::createEvent();
        $event->setLevel(self::getSeverityFromLevel($record['level']));
        $event->setMessage($record['message']);
        $event->setLogger(sprintf('monolog.%s', $record['channel']));

        $hint = new EventHint();

        if (isset($record['context']['exception']) && $record['context']['exception'] instanceof \Throwable) {
            $hint->exception = $record['context']['exception'];
        }

        $this->hub->withScope(function (Scope $scope) use ($record, $event, $hint): void {
            $scope->setExtra('monolog.channel', $record['channel']);
            $scope->setExtra('monolog.level', $record['level_name']);

            $this->hub->captureEvent($event, $hint);
        });
    }

    private static function getSeverityFromLevel(int $level): Severity
    {
        switch ($level) {
            case Logger::DEBUG:
                return Severity::debug();
            case Logger::WARNING:
                return Severity::warning();
            case Logger::ERROR:
                return Severity::error();
            case Logger::CRITICAL:
            case Logger::ALERT:
            case Logger::EMERGENCY:
                return Severity::fatal();
            case Logger::INFO:
            case Logger::NOTICE:
            default:
                return Severity::info();
        }
    }
}
