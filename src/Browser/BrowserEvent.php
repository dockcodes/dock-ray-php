<?php

declare(strict_types=1);

namespace Dock\Ray\Browser;

use Dock\Ray\Event;
use Dock\Ray\ExceptionDataBag;
use Dock\Ray\EventId;
use Dock\Ray\Frame;
use Dock\Ray\Severity;
use Dock\Ray\Stacktrace;

/**
 * Zamienia zgłoszenie z przeglądarki na zdarzenie SDK.
 *
 * Przeglądarka nie ma jak uwierzytelnić się w panelu — klucz prywatny projektu
 * musiałby wtedy wisieć w kodzie strony, widoczny dla każdego. Dlatego błąd JS
 * jedzie najpierw do aplikacji (WordPress, PrestaShop, Drupal, Joomla), a ta
 * przekazuje go dalej własnym kluczem. Ta klasa jest granicą między jednym
 * a drugim: wszystko, co przyszło z przeglądarki, jest tu niezaufane.
 */
final class BrowserEvent
{
    private const MAX_STRING = 1024;

    private const MAX_FRAMES = 50;

    public static function fromArray(array $payload, ?string $pageUrl = null, ?string $userAgent = null): ?Event
    {
        $type = self::text($payload['type'] ?? '') ?: 'Error';
        $message = self::text($payload['message'] ?? '');

        if ($message === '') {
            return null;
        }

        $event = Event::createEvent(EventId::generate());
        $event->setLevel(self::level(self::text($payload['level'] ?? '')));
        $event->setPlatform('javascript');
        $event->setExceptions([self::exception($type, $message, $payload['stack'] ?? null)]);
        $event->setRequest(array_filter([
            'url' => self::text($payload['url'] ?? $pageUrl ?? ''),
            'method' => 'GET',
        ]));

        $event->setContext('browser', array_filter([
            'name' => self::text($payload['browser'] ?? ''),
            'user_agent' => self::text($userAgent ?? ''),
        ]));

        $tags = ['source' => 'browser'];

        if ($release = self::text($payload['release'] ?? '')) {
            $event->setRelease($release);
        }

        foreach (['handler', 'referrer'] as $key) {
            if ($value = self::text($payload[$key] ?? '')) {
                $tags[$key] = $value;
            }
        }

        $event->setTags($tags);

        return $event;
    }

    private static function exception(string $type, string $message, mixed $stack): ExceptionDataBag
    {
        return ExceptionDataBag::create($type, $message, self::stacktrace($stack));
    }

    /**
     * Ramki przychodzą jako tablica z kolektora; ślad z `Error.stack` bywa
     * zminifikowany i wtedy niesie tyle, co nic, więc nie próbujemy go parsować
     * po stronie serwera.
     */
    private static function stacktrace(mixed $stack): ?Stacktrace
    {
        if (! is_array($stack) || $stack === []) {
            return null;
        }

        $frames = [];

        foreach (array_slice($stack, 0, self::MAX_FRAMES) as $frame) {
            if (! is_array($frame)) {
                continue;
            }

            $file = self::text($frame['filename'] ?? '');

            if ($file === '') {
                continue;
            }

            $frames[] = new Frame(
                self::text($frame['function'] ?? '') ?: '?',
                $file,
                max(0, (int) ($frame['lineno'] ?? 0)),
                null,
                $file,
                [],
                self::isInApp($file)
            );
        }

        // Ramki w zdarzeniu idą od najstarszej; przeglądarka podaje odwrotnie.
        return $frames === [] ? null : new Stacktrace(array_reverse($frames));
    }

    private static function isInApp(string $file): bool
    {
        foreach (['/wp-includes/', '/wp-admin/', 'node_modules', 'cdn.', 'googletagmanager', 'gtag/js'] as $vendor) {
            if (str_contains($file, $vendor)) {
                return false;
            }
        }

        return true;
    }

    private static function level(string $level): Severity
    {
        return match ($level) {
            'debug' => Severity::debug(),
            'info' => Severity::info(),
            'warning' => Severity::warning(),
            'fatal' => Severity::fatal(),
            default => Severity::error(),
        };
    }

    private static function text(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        return trim(mb_substr((string) $value, 0, self::MAX_STRING));
    }
}
