<?php

declare(strict_types=1);

namespace Dock\Ray\Integration;

use Dock\Ray\Event;
use Dock\Ray\RaySdk;
use Dock\Ray\State\Scope;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class IgnoreErrorsIntegration implements IntegrationInterface
{
    private $options;

    public function __construct(array $options = [])
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'ignore_exceptions' => [],
            'ignore_tags' => [],
        ]);

        $resolver->setAllowedTypes('ignore_exceptions', ['array']);
        $resolver->setAllowedTypes('ignore_tags', ['array']);

        $this->options = $resolver->resolve($options);
    }

    
    public function setupOnce(): void
    {
        Scope::addGlobalEventProcessor(static function (Event $event): ?Event {
            $integration = RaySdk::getCurrentHub()->getIntegration(self::class);

            if (null !== $integration && $integration->shouldDropEvent($event, $integration->options)) {
                return null;
            }

            return $event;
        });
    }

   
    private function shouldDropEvent(Event $event, array $options): bool
    {
        if ($this->isIgnoredException($event, $options)) {
            return true;
        }

        if ($this->isIgnoredTag($event, $options)) {
            return true;
        }

        return false;
    }

    private function isIgnoredException(Event $event, array $options): bool
    {
        $exceptions = $event->getExceptions();

        if (empty($exceptions)) {
            return false;
        }

        foreach ($options['ignore_exceptions'] as $ignoredException) {
            if (is_a($exceptions[0]->getType(), $ignoredException, true)) {
                return true;
            }
        }

        return false;
    }

    private function isIgnoredTag(Event $event, array $options): bool
    {
        $tags = $event->getTags();

        if (empty($tags)) {
            return false;
        }

        foreach ($options['ignore_tags'] as $key => $value) {
            if (isset($tags[$key]) && $tags[$key] === $value) {
                return true;
            }
        }

        return false;
    }
}
