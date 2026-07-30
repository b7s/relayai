<?php

namespace App\Support;

/**
 * Minimal JSONC (JSON with comments) codec.
 *
 * Strips `//` line comments and `/* ... *‍/` block comments while respecting
 * string literals, so the result can be fed to the native JSON parser.
 */
final class Jsonc
{
    /** @return array<int|string, mixed> */
    public static function decode(string $jsonc): array
    {
        $decoded = json_decode(self::stripComments($jsonc), true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param  array<int|string, mixed>  $data */
    public static function encode(array $data): string
    {
        return json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ).PHP_EOL;
    }

    public static function stripComments(string $jsonc): string
    {
        $output = '';
        $length = strlen($jsonc);
        $inString = false;
        $escaped = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $jsonc[$i];

            if ($inString) {
                $output .= $char;

                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            $next = $i + 1 < $length ? $jsonc[$i + 1] : '';

            if ($char === '"') {
                $inString = true;
                $output .= $char;

                continue;
            }

            if ($char === '/' && $next === '/') {
                while ($i < $length && $jsonc[$i] !== "\n") {
                    $i++;
                }

                continue;
            }

            if ($char === '/' && $next === '*') {
                $i += 2;

                while ($i < $length && ! ($jsonc[$i] === '*' && ($i + 1 < $length && $jsonc[$i + 1] === '/'))) {
                    $i++;
                }

                $i++;

                continue;
            }

            $output .= $char;
        }

        return $output;
    }
}
