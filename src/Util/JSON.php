<?php

declare(strict_types=1);

namespace Dock\Ray\Util;

use Dock\Ray\Exception\JsonException;

final class JSON
{
    public static function encode($data, int $options = 0, int $maxDepth = 512): string
    {
        if ($maxDepth < 1) {
            throw new \InvalidArgumentException('The $maxDepth argument must be an integer greater than 0.');
        }

        $options |= \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE;

        $encodedData = json_encode($data, $options, $maxDepth);

        if (\JSON_ERROR_NONE !== json_last_error()) {
            throw new JsonException(sprintf('Could not encode value into JSON format. Error was: "%s".', json_last_error_msg()));
        }

        return $encodedData;
    }

    public static function decode(string $data)
    {
        $decodedData = json_decode($data, true);

        if (\JSON_ERROR_NONE !== json_last_error()) {
            throw new JsonException(sprintf('Could not decode value from JSON format. Error was: "%s".', json_last_error_msg()));
        }

        return $decodedData;
    }
}
