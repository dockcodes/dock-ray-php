<?php

declare(strict_types=1);

namespace Dock\Thor\Browser;

/**
 * Wskazuje plik kolektora i buduje jego konfigurację.
 *
 * Skrypt leży w paczce SDK, a nie w każdej wtyczce z osobna — kopie w czterech
 * repozytoriach rozjeżdżają się przy pierwszej poprawce. Wtyczka pyta o
 * ścieżkę i sama zamienia ją na adres, bo tylko ona wie, jak jej katalog
 * mapuje się na URL.
 */
final class Collector
{
    public static function scriptPath(): string
    {
        return \dirname(__DIR__, 2) . '/browser/thor-browser.js';
    }

    public static function scriptVersion(): string
    {
        $path = self::scriptPath();

        return is_file($path) ? (string) filemtime($path) : '1';
    }

    /**
     * @param string[] $ignore
     */
    public static function config(
        string $endpoint,
        ?string $token = null,
        ?string $release = null,
        float $sampleRate = 1.0,
        int $maxEvents = 10,
        array $ignore = [],
    ): array {
        return array_filter([
            'endpoint' => $endpoint,
            'token' => $token,
            'release' => $release,
            'sampleRate' => max(0.0, min(1.0, $sampleRate)),
            'maxEvents' => max(1, $maxEvents),
            'ignore' => array_values(array_filter($ignore, 'is_string')),
        ], static fn ($value): bool => $value !== null && $value !== []);
    }

    /**
     * Domyślnie wyciszane szumy: ostrzeżenie ResizeObserver, które przeglądarki
     * zgłaszają jako błąd bez powodu, oraz błędy skryptów z obcych domen,
     * o których i tak nie dowiemy się niczego poza słowem „Script error".
     */
    public static function defaultIgnoreList(): array
    {
        return [
            'ResizeObserver loop',
            'Script error.',
            'Non-Error promise rejection captured',
        ];
    }
}
