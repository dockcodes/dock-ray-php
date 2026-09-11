<?php

declare(strict_types=1);

namespace Dock\Ray\Util;

/**
 * Odpowiedniki funkcji, które weszły do PHP dopiero w wersji 8.0.
 *
 * SDK działa od PHP 7.4, bo tyle mają wtyczki do sklepów i CMS-ów stojące
 * u klientów. Zamiast wozić polyfill Symfony trzymamy tu trzy funkcje,
 * których naprawdę używamy — cały plik jest krótszy od jego autoloadera.
 */
final class Compat
{
    private function __construct() {}

    public static function startsWith(string $haystack, string $needle): bool
    {
        return '' === $needle || 0 === strncmp($haystack, $needle, \strlen($needle));
    }

    public static function contains(string $haystack, string $needle): bool
    {
        return '' === $needle || false !== strpos($haystack, $needle);
    }

    /**
     * Odpowiednik `get_debug_type()`: nazwa typu do komunikatu wyjątku.
     *
     * @param mixed $value
     */
    public static function typeName($value): string
    {
        if (null === $value) {
            return 'null';
        }

        if (\is_bool($value)) {
            return 'bool';
        }

        if (\is_int($value)) {
            return 'int';
        }

        if (\is_float($value)) {
            return 'float';
        }

        if (\is_string($value)) {
            return 'string';
        }

        if (\is_array($value)) {
            return 'array';
        }

        if (\is_object($value)) {
            $class = \get_class($value);

            return false === strpos($class, '@anonymous') ? $class : 'class@anonymous';
        }

        if (\is_resource($value)) {
            return 'resource (' . get_resource_type($value) . ')';
        }

        return 'resource (closed)';
    }
}
