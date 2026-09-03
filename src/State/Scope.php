<?php

declare(strict_types=1);

namespace Dock\Ray\State;

use Dock\Ray\Breadcrumb;
use Dock\Ray\Event;
use Dock\Ray\EventHint;
use Dock\Ray\Severity;
use Dock\Ray\Tracing\Span;
use Dock\Ray\Tracing\Transaction;
use Dock\Ray\UserDataBag;

final class Scope
{
    /**
     * @var array
     */
    private $breadcrumbs = [];

    /**
     * @var UserDataBag|null
     */
    private $user = null;

    /**
     * @var array
     */
    private $contexts = [];

    /**
     * @var array
     */
    private $tags = [];

    /**
     * @var array
     */
    private $extra = [];

    /**
     * @var array
     */
    private $fingerprint = [];

    /**
     * @var Severity|null
     */
    private $level = null;

    /**
     * @var array
     */
    private $eventProcessors = [];

    /**
     * @var Span|null
     */
    private $span = null;

    /**
     * @var array
     */
    private static $globalEventProcessors = [];

    public function setTag(string $key, string $value): self
    {
        $this->tags[$key] = $value;

        return $this;
    }

    public function setTags(array $tags): self
    {
        $this->tags = array_merge($this->tags, $tags);

        return $this;
    }

    public function removeTag(string $key): self
    {
        unset($this->tags[$key]);

        return $this;
    }
    
    public function setContext(string $name, array $value): self
    {
        $this->contexts[$name] = $value;

        return $this;
    }

    public function removeContext(string $name): self
    {
        unset($this->contexts[$name]);

        return $this;
    }

    public function setExtra(string $key, $value): self
    {
        $this->extra[$key] = $value;

        return $this;
    }

    public function setExtras(array $extras): self
    {
        $this->extra = array_merge($this->extra, $extras);

        return $this;
    }

    public function setUser($user): self
    {
        if (!\is_array($user) && !$user instanceof UserDataBag) {
            throw new \TypeError(sprintf('The $user argument must be either an array or an instance of the "%s" class. Got: "%s".', UserDataBag::class, get_debug_type($user)));
        }

        if (\is_array($user)) {
            $user = UserDataBag::createFromArray($user);
        }

        if (null === $this->user) {
            $this->user = $user;
        } else {
            $this->user = $this->user->merge($user);
        }

        return $this;
    }
    
    public function removeUser(): self
    {
        $this->user = null;

        return $this;
    }

    public function setFingerprint(array $fingerprint): self
    {
        $this->fingerprint = $fingerprint;

        return $this;
    }

    public function setLevel(?Severity $level): self
    {
        $this->level = $level;

        return $this;
    }

    public function addBreadcrumb(Breadcrumb $breadcrumb, int $maxBreadcrumbs = 100): self
    {
        $this->breadcrumbs[] = $breadcrumb;
        $this->breadcrumbs = \array_slice($this->breadcrumbs, -$maxBreadcrumbs);

        return $this;
    }

    public function clearBreadcrumbs(): self
    {
        $this->breadcrumbs = [];

        return $this;
    }

    public function addEventProcessor(callable $eventProcessor): self
    {
        $this->eventProcessors[] = $eventProcessor;

        return $this;
    }

    public static function addGlobalEventProcessor(callable $eventProcessor): void
    {
        self::$globalEventProcessors[] = $eventProcessor;
    }

    public function clear(): self
    {
        $this->user = null;
        $this->level = null;
        $this->span = null;
        $this->fingerprint = [];
        $this->breadcrumbs = [];
        $this->tags = [];
        $this->extra = [];
        $this->contexts = [];

        return $this;
    }

    public function applyToEvent(Event $event, ?EventHint $hint = null): ?Event
    {
        $event->setFingerprint(array_merge($event->getFingerprint(), $this->fingerprint));

        if (empty($event->getBreadcrumbs())) {
            $event->setBreadcrumb($this->breadcrumbs);
        }

        if (null !== $this->level) {
            $event->setLevel($this->level);
        }

        if (!empty($this->tags)) {
            $event->setTags(array_merge($this->tags, $event->getTags()));
        }

        if (!empty($this->extra)) {
            $event->setExtra(array_merge($this->extra, $event->getExtra()));
        }

        if (null !== $this->user) {
            $user = $event->getUser();

            if (null === $user) {
                $user = $this->user;
            } else {
                $user = $this->user->merge($user);
            }

            $event->setUser($user);
        }

        // We do this here to also apply the trace context to errors if there is a Span on the Scope
        if (null !== $this->span) {
            $event->setContext('trace', $this->span->getTraceContext());
        }

        foreach (array_merge($this->contexts, $event->getContexts()) as $name => $data) {
            $event->setContext($name, $data);
        }

        // We create a empty `EventHint` instance to allow processors to always receive a `EventHint` instance even if there wasn't one
        if (null === $hint) {
            $hint = new EventHint();
        }

        foreach (array_merge(self::$globalEventProcessors, $this->eventProcessors) as $processor) {
            $event = $processor($event, $hint);

            if (null === $event) {
                return null;
            }

            if (!$event instanceof Event) {
                throw new \InvalidArgumentException(sprintf('The event processor must return null or an instance of the %s class', Event::class));
            }
        }

        return $event;
    }

    public function getSpan(): ?Span
    {
        return $this->span;
    }

    public function setSpan(?Span $span): self
    {
        $this->span = $span;

        return $this;
    }

    public function getTransaction(): ?Transaction
    {
        $span = $this->span;

        if (null !== $span && null !== $span->getSpanRecorder() && !empty($span->getSpanRecorder()->getSpans())) {
            /** @var Transaction */
            return $span->getSpanRecorder()->getSpans()[0];
        }

        return null;
    }

    public function __clone()
    {
        if (null !== $this->user) {
            $this->user = clone $this->user;
        }
    }
}
