<?php

declare(strict_types=1);

namespace Dock\Thor\Framework;

/**
 * Oddaje odpowiedź przeglądarce przed wykonaniem dalszej pracy.
 *
 * Raportowanie kosztuje żądanie HTTP do panelu. Wykonane w trakcie obsługi
 * strony doliczyłoby ten czas do każdego błędu, który akurat zdarzył się
 * użytkownikowi — czyli spowalniało aplikację dokładnie wtedy, gdy i tak jest
 * źle. PHP-FPM (nginx) i LiteSpeed potrafią zamknąć połączenie i zostawić
 * proces przy pracy; tam gdzie nie potrafią, zostaje zwykłe opróżnienie
 * buforów, a wysyłka i tak dzieje się po całej obsłudze żądania.
 */
final class ResponseFlusher
{
    private static bool $flushed = false;

    public static function flush(): bool
    {
        if (self::$flushed || \PHP_SAPI === 'cli') {
            return false;
        }

        self::$flushed = true;

        if (\function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();

            return true;
        }

        if (\function_exists('litespeed_finish_request')) {
            litespeed_finish_request();

            return true;
        }

        return self::closeConnectionManually();
    }

    public static function supportsEarlyClose(): bool
    {
        return \function_exists('fastcgi_finish_request') || \function_exists('litespeed_finish_request');
    }

    /**
     * Ostatnia deska ratunku dla mod_php. Deklarujemy długość odpowiedzi
     * i zamykamy połączenie, żeby przeglądarka nie czekała. Bez wysłanych
     * nagłówków się nie da, a przy wielu poziomach buforowania nie wiemy, co
     * jeszcze dopisze aplikacja — wtedy odpuszczamy i zostaje samo `flush()`.
     */
    private static function closeConnectionManually(): bool
    {
        @ignore_user_abort(true);

        if (headers_sent() || ob_get_level() !== 1) {
            self::flushBuffers();

            return false;
        }

        $length = ob_get_length();

        if ($length === false) {
            self::flushBuffers();

            return false;
        }

        header('Connection: close');
        header('Content-Length: ' . $length);

        self::flushBuffers();

        return true;
    }

    private static function flushBuffers(): void
    {
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }

        @flush();
    }
}
