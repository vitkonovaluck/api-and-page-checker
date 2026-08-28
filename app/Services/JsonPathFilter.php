<?php

declare(strict_types=1);

namespace App\Services;

final class JsonPathFilter
{
    public static function matches(string $path, string $pattern): bool
    {
        $pathSegments = self::segments($path);
        $patternSegments = self::segments($pattern);

        if (count($pathSegments) !== count($patternSegments)) {
            return false;
        }

        foreach ($patternSegments as $index => $expected) {
            $actual = $pathSegments[$index];

            if ($expected === '*' || $expected === $actual) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * @param  list<string>  $patterns
     */
    public static function matchesAny(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (self::matches($path, (string) $pattern)) {
                return true;
            }
        }

        return false;
    }

    public static function get(mixed $data, string $path): mixed
    {
        return self::resolve($data, self::segments($path));
    }

    /**
     * @param  list<string>  $paths
     */
    public static function extract(mixed $data, array $paths): mixed
    {
        if ($paths === []) {
            return $data;
        }

        $result = [];

        foreach ($paths as $path) {
            $extracted = self::extractPath($data, self::segments((string) $path));

            if (! is_array($extracted)) {
                continue;
            }

            $result = array_replace_recursive($result, $extracted);
        }

        return $result;
    }

    /**
     * @param  list<string>  $paths
     */
    public static function remove(mixed $data, array $paths): mixed
    {
        if (! is_array($data) || $paths === []) {
            return $data;
        }

        foreach ($paths as $path) {
            $data = self::removePath($data, self::segments((string) $path));
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    public static function segments(string $path): array
    {
        $path = trim($path);

        if (str_starts_with($path, '$')) {
            $path = substr($path, 1);
        }

        $path = ltrim($path, '.');

        if ($path === '') {
            return [];
        }

        preg_match_all('/\[(\*|\d+)\]|[^.\[\]]+/', $path, $matches, PREG_SET_ORDER);

        $segments = [];

        foreach ($matches as $match) {
            $segments[] = ($match[1] ?? '') !== '' ? $match[1] : $match[0];
        }

        return $segments;
    }

    /**
     * @param  list<string>  $segments
     */
    private static function resolve(mixed $data, array $segments): mixed
    {
        if ($segments === []) {
            return $data;
        }

        $head = array_shift($segments);

        if ($head === '*') {
            if (! is_array($data)) {
                return null;
            }

            $values = [];

            foreach ($data as $item) {
                $values[] = self::resolve($item, $segments);
            }

            return $values;
        }

        if (! is_array($data)) {
            return null;
        }

        $key = self::arrayKey($head);

        if (! array_key_exists($key, $data)) {
            return null;
        }

        return self::resolve($data[$key], $segments);
    }

    /**
     * @param  list<string>  $segments
     */
    private static function extractPath(mixed $data, array $segments): mixed
    {
        if ($segments === []) {
            return $data;
        }

        $head = array_shift($segments);

        if ($head === '*') {
            if (! is_array($data)) {
                return [];
            }

            $items = [];

            foreach ($data as $item) {
                $items[] = self::extractPath($item, $segments);
            }

            return $items;
        }

        $key = self::arrayKey($head);

        if (! is_array($data) || ! array_key_exists($key, $data)) {
            return [];
        }

        return [$key => self::extractPath($data[$key], $segments)];
    }

    /**
     * @param  list<string>  $segments
     */
    private static function removePath(mixed $data, array $segments): mixed
    {
        if (! is_array($data) || $segments === []) {
            return $data;
        }

        $head = array_shift($segments);

        if ($head === '*' || $head === '[*]') {
            foreach ($data as $index => $item) {
                $data[$index] = self::removePath($item, $segments);
            }

            return $data;
        }

        $key = self::arrayKey($head);

        if ($segments === []) {
            unset($data[$key]);

            return $data;
        }

        if (array_key_exists($key, $data)) {
            $data[$key] = self::removePath($data[$key], $segments);
        }

        return $data;
    }

    private static function arrayKey(string $segment): string|int
    {
        $segment = trim($segment, '[]');

        if (is_numeric($segment)) {
            return (int) $segment;
        }

        return $segment;
    }
}
