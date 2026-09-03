<?php

declare(strict_types=1);

namespace Dock\Ray;

use GuzzleHttp\Promise\PromiseInterface;
use Jean85\PrettyVersions;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Dock\Ray\Integration\IntegrationInterface;
use Dock\Ray\Integration\IntegrationRegistry;
use Dock\Ray\Serializer\RepresentationSerializer;
use Dock\Ray\Serializer\RepresentationSerializerInterface;
use Dock\Ray\Serializer\SerializerInterface;
use Dock\Ray\State\Scope;
use Dock\Ray\Transport\TransportInterface;

final class Client implements ClientInterface
{
    public const PROTOCOL_VERSION = '1';

    public const SDK_IDENTIFIER = 'ray.php';

    public const SDK_VERSION = '1.2.0';

    /**
     * @var Options
     */
    private $options;

    /**
     * @var TransportInterface
     */
    private $transport;

    /**
     * @var LoggerInterface
     */
    private $logger;
    
    private $integrations;

    /**
     * @var RepresentationSerializerInterface
     */
    private $representationSerializer;
    
    /**
     * @var StacktraceBuilder
     */
    private $stacktraceBuilder;

    /**
     * @var string
     */
    private $sdkIdentifier;
    
    /**
     * @var string
     */
    private $sdkVersion;

    public function __construct(
        Options $options,
        TransportInterface $transport,
        ?string $sdkIdentifier = null,
        ?string $sdkVersion = null,
        ?SerializerInterface $serializer = null,
        ?RepresentationSerializerInterface $representationSerializer = null,
        ?LoggerInterface $logger = null
    ) {
        $this->options = $options;
        $this->transport = $transport;
        $this->logger = $logger ?? new NullLogger();
        $this->integrations = IntegrationRegistry::getInstance()->setupIntegrations($options, $this->logger);
        $this->representationSerializer = $representationSerializer ?? new RepresentationSerializer($this->options);
        $this->stacktraceBuilder = new StacktraceBuilder($options, $this->representationSerializer);
        $this->sdkIdentifier = $sdkIdentifier ?? self::SDK_IDENTIFIER;
        $this->sdkVersion = $sdkVersion ?? PrettyVersions::getVersion('dockcodes/dock-ray')->getPrettyVersion();
    }

    public function getOptions(): Options
    {
        return $this->options;
    }

    public function captureMessage(string $message, ?Severity $level = null, ?Scope $scope = null, ?EventHint $hint = null): ?EventId
    {
        $event = Event::createEvent();
        $event->setMessage($message);
        $event->setLevel($level);

        return $this->captureEvent($event, $hint, $scope);
    }

    public function captureException(\Throwable $exception, ?Scope $scope = null, ?EventHint $hint = null): ?EventId
    {
        $hint = $hint ?? new EventHint();

        if (null === $hint->exception) {
            $hint->exception = $exception;
        }

        return $this->captureEvent(Event::createEvent(), $hint, $scope);
    }

    public function captureEvent(Event $event, ?EventHint $hint = null, ?Scope $scope = null): ?EventId
    {
        $event = $this->prepareEvent($event, $hint, $scope);

        if (null === $event) {
            return null;
        }

        try {
            /** @var Response $response */
            $response = $this->transport->send($event)->wait();
            $event = $response->getEvent();

            if (null !== $event) {
                return $event->getId();
            }
        } catch (\Throwable $exception) {
        }

        return null;
    }

    public function captureLastError(?Scope $scope = null, ?EventHint $hint = null): ?EventId
    {
        $error = error_get_last();

        if (null === $error || !isset($error['message'][0])) {
            return null;
        }

        $exception = new \ErrorException(@$error['message'], 0, @$error['type'], @$error['file'], @$error['line']);

        return $this->captureException($exception, $scope, $hint);
    }
    
    public function getIntegration(string $className): ?IntegrationInterface
    {
        return $this->integrations[$className] ?? null;
    }

    public function flush(?int $timeout = null): PromiseInterface
    {
        return $this->transport->close($timeout);
    }
    
    private function prepareEvent(Event $event, ?EventHint $hint = null, ?Scope $scope = null): ?Event
    {
        if (null !== $hint) {
            if (null !== $hint->exception && empty($event->getExceptions())) {
                $this->addThrowableToEvent($event, $hint->exception);
            }

            if (null !== $hint->stacktrace && null === $event->getStacktrace()) {
                $event->setStacktrace($hint->stacktrace);
            }
        }

        $this->addMissingStacktraceToEvent($event);

        $event->setSdkIdentifier($this->sdkIdentifier);
        $event->setSdkVersion($this->sdkVersion);

        if (null === $event->getServerName()) {
            $event->setServerName($this->options->getServerName());
        }

        if (null === $event->getRelease()) {
            $event->setRelease($this->options->getRelease());
        }

        if (null === $event->getEnvironment()) {
            $event->setEnvironment($this->options->getEnvironment() ?? Event::DEFAULT_ENVIRONMENT);
        }

        if (null === $event->getLogger()) {
            $event->setLogger($this->options->getLogger());
        }

        $isTransaction = EventType::transaction() === $event->getType();
        $sampleRate = $this->options->getSampleRate();

        if (!$isTransaction && $sampleRate < 1 && mt_rand(1, 100) / 100.0 > $sampleRate) {
            $this->logger->info('The event will be discarded because it has been sampled.', ['event' => $event]);

            return null;
        }

        if (null !== $scope) {
            $previousEvent = $event;
            $event = $scope->applyToEvent($event, $hint);

            if (null === $event) {
                $this->logger->info('The event will be discarded because one of the event processors returned "null".', ['event' => $previousEvent]);

                return null;
            }
        }

        if (!$isTransaction) {
            $previousEvent = $event;
            $event = ($this->options->getBeforeSendCallback())($event, $hint);

            if (null === $event) {
                $this->logger->info('The event will be discarded because the "before_send" callback returned "null".', ['event' => $previousEvent]);
            }
        }

        return $event;
    }

    private function addMissingStacktraceToEvent(Event $event): void
    {
        if (!$this->options->shouldAttachStacktrace()) {
            return;
        }

        // We should not add a stacktrace when the event already has one or contains exceptions
        if (null !== $event->getStacktrace() || !empty($event->getExceptions())) {
            return;
        }

        $event->setStacktrace($this->stacktraceBuilder->buildFromBacktrace(
            debug_backtrace(0),
            __FILE__,
            __LINE__ - 3
        ));
    }

    private function addThrowableToEvent(Event $event, \Throwable $exception): void
    {
        if ($exception instanceof \ErrorException && null === $event->getLevel()) {
            $event->setLevel(Severity::fromError($exception->getSeverity()));
        }

        $exceptions = [];

        do {
            $exceptions[] = new ExceptionDataBag(
                $exception,
                $this->stacktraceBuilder->buildFromException($exception),
                new ExceptionMechanism(ExceptionMechanism::TYPE_GENERIC, true)
            );
        } while ($exception = $exception->getPrevious());

        $event->setExceptions($exceptions);
    }
}
