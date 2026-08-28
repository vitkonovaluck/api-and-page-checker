<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\DiffOptionsDTO;
use App\Enums\AddressKind;
use App\Enums\DiffClassification;
use App\Models\Snapshot;
use Symfony\Component\Yaml\Yaml;

class DiffService
{
    /**
     * @return array{
     *     has_changes: bool,
     *     is_first: bool,
     *     classification: string,
     *     schema_changes: int,
     *     value_changes: int,
     *     status_code: array{old: ?int, new: ?int, changed: bool},
     *     response_time_ms: array{old: ?int, new: int, delta: ?int},
     *     headers: list<array{key: string, type: string, old: ?string, new: ?string}>,
     *     body: array{
     *         type: string,
     *         changed: bool,
     *         classification: string,
     *         changes: list<array{path: string, type: string, category: string, old: mixed, new: mixed}>,
     *         text_diff: list<string>
     *     },
     *     error_message: array{old: ?string, new: ?string, changed: bool}
     * }
     */
    public function compare(?Snapshot $previous, Snapshot $current, ?DiffOptionsDTO $options = null): array
    {
        $options ??= DiffOptionsDTO::empty();

        if ($previous === null) {
            return $this->firstSnapshot($current);
        }

        $statusChanged = $previous->status_code !== $current->status_code;
        $errorChanged = $previous->error_message !== $current->error_message;
        $headerChanges = $this->diffHeaders(
            $previous->headers ?? [],
            $current->headers ?? [],
            $options->ignoreHeaders,
        );
        $bodyDiff = $this->diffBody($previous->body ?? '', $current->body ?? '', $options);

        $hasChanges = $statusChanged
            || $errorChanged
            || $headerChanges !== []
            || $bodyDiff['changed'];

        return [
            'has_changes' => $hasChanges,
            'is_first' => false,
            'classification' => $bodyDiff['classification'],
            'schema_changes' => $this->countCategory($bodyDiff['changes'], 'schema'),
            'value_changes' => $this->countCategory($bodyDiff['changes'], 'value'),
            'status_code' => [
                'old' => $previous->status_code,
                'new' => $current->status_code,
                'changed' => $statusChanged,
            ],
            'response_time_ms' => [
                'old' => $previous->response_time_ms,
                'new' => $current->response_time_ms,
                'delta' => $current->response_time_ms - $previous->response_time_ms,
            ],
            'headers' => $headerChanges,
            'body' => $bodyDiff,
            'error_message' => [
                'old' => $previous->error_message,
                'new' => $current->error_message,
                'changed' => $errorChanged,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function firstSnapshot(Snapshot $current): array
    {
        return [
            'has_changes' => true,
            'is_first' => true,
            'classification' => DiffClassification::None->value,
            'schema_changes' => 0,
            'value_changes' => 0,
            'status_code' => [
                'old' => null,
                'new' => $current->status_code,
                'changed' => true,
            ],
            'response_time_ms' => [
                'old' => null,
                'new' => $current->response_time_ms,
                'delta' => null,
            ],
            'headers' => [],
            'body' => [
                'type' => 'none',
                'changed' => true,
                'classification' => DiffClassification::None->value,
                'changes' => [],
                'text_diff' => [],
                'old_preview' => '',
                'new_preview' => $this->normalizeForDisplay($current->body ?? ''),
            ],
            'error_message' => [
                'old' => null,
                'new' => $current->error_message,
                'changed' => $current->error_message !== null,
            ],
        ];
    }

    /**
     * @param  array<string, string>  $old
     * @param  array<string, string>  $new
     * @param  list<string>  $ignoreHeaders
     * @return list<array{key: string, type: string, old: ?string, new: ?string}>
     */
    private function diffHeaders(array $old, array $new, array $ignoreHeaders): array
    {
        $ignored = array_map(strtolower(...), $ignoreHeaders);
        $changes = [];
        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
        sort($keys);

        foreach ($keys as $key) {
            if (in_array(strtolower((string) $key), $ignored, true)) {
                continue;
            }

            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;

            if ($oldValue === $newValue) {
                continue;
            }

            if ($oldValue === null) {
                $changes[] = ['key' => $key, 'type' => 'added', 'old' => null, 'new' => $newValue];
            } elseif ($newValue === null) {
                $changes[] = ['key' => $key, 'type' => 'removed', 'old' => $oldValue, 'new' => null];
            } else {
                $changes[] = ['key' => $key, 'type' => 'changed', 'old' => $oldValue, 'new' => $newValue];
            }
        }

        return $changes;
    }

    /**
     * @return array{
     *     type: string,
     *     changed: bool,
     *     classification: string,
     *     changes: list<array{path: string, type: string, category: string, old: mixed, new: mixed}>,
     *     text_diff: list<string>,
     *     old_preview: string,
     *     new_preview: string
     * }
     */
    private function diffBody(string $oldBody, string $newBody, DiffOptionsDTO $options): array
    {
        $oldBody = $this->stripRegex($oldBody, $options->ignoreBodyRegex);
        $newBody = $this->stripRegex($newBody, $options->ignoreBodyRegex);
        $oldPreview = $this->normalizeForDisplay($oldBody);
        $newPreview = $this->normalizeForDisplay($newBody);

        if (hash('sha256', $oldBody) === hash('sha256', $newBody)) {
            return $this->unchangedBody($oldPreview, $newPreview);
        }

        if ($options->kind === AddressKind::OpenApi) {
            $oldJson = $this->normalizeOpenApi($oldBody);
            $newJson = $this->normalizeOpenApi($newBody);
        } else {
            $oldJson = $this->decodeJson($oldBody);
            $newJson = $this->decodeJson($newBody);
        }

        if ($oldJson !== null && $newJson !== null) {
            return $this->jsonBodyDiff($oldJson, $newJson, $options, $oldPreview, $newPreview);
        }

        $textDiff = $this->lineDiff($oldPreview, $newPreview);

        return [
            'type' => 'text',
            'changed' => $textDiff !== [],
            'classification' => $textDiff === [] ? DiffClassification::None->value : DiffClassification::ValueChange->value,
            'changes' => [],
            'text_diff' => $textDiff,
            'old_preview' => $oldPreview,
            'new_preview' => $newPreview,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unchangedBody(string $oldPreview, string $newPreview): array
    {
        return [
            'type' => 'none',
            'changed' => false,
            'classification' => DiffClassification::None->value,
            'changes' => [],
            'text_diff' => [],
            'old_preview' => $oldPreview,
            'new_preview' => $newPreview,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonBodyDiff(mixed $oldJson, mixed $newJson, DiffOptionsDTO $options, string $oldPreview, string $newPreview): array
    {
        if ($options->watchJsonPaths !== []) {
            $oldJson = JsonPathFilter::extract($oldJson, $options->watchJsonPaths);
            $newJson = JsonPathFilter::extract($newJson, $options->watchJsonPaths);
        }

        $oldJson = JsonPathFilter::remove($oldJson, $options->ignoreJsonPaths);
        $newJson = JsonPathFilter::remove($newJson, $options->ignoreJsonPaths);

        $changes = [];
        $this->walkJsonDiff($oldJson, $newJson, '', $changes);

        $classification = $this->classify($changes);

        return [
            'type' => 'json',
            'changed' => $changes !== [],
            'classification' => $classification->value,
            'changes' => $changes,
            'text_diff' => $changes !== [] ? $this->lineDiff($oldPreview, $newPreview) : [],
            'old_preview' => $oldPreview,
            'new_preview' => $newPreview,
        ];
    }

    /**
     * @param  list<string>  $patterns
     */
    private function stripRegex(string $body, array $patterns): string
    {
        foreach ($patterns as $pattern) {
            $wrapped = str_starts_with($pattern, '/') ? $pattern : '/'.$pattern.'/u';
            $replaced = @preg_replace($wrapped, '', $body);

            if (is_string($replaced)) {
                $body = $replaced;
            }
        }

        return $body;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeOpenApi(string $body): ?array
    {
        $decoded = $this->decodeJson($body);

        if ($decoded === null && class_exists(Yaml::class)) {
            try {
                $decoded = Yaml::parse($body);
            } catch (\Throwable) {
                $decoded = null;
            }
        }

        if (! is_array($decoded)) {
            return null;
        }

        $paths = [];

        foreach (($decoded['paths'] ?? []) as $path => $methods) {
            if (! is_array($methods)) {
                continue;
            }

            foreach ($methods as $method => $operation) {
                if (! is_string($method) || ! is_array($operation)) {
                    continue;
                }

                $paths[strtoupper($method).' '.$path] = [
                    'parameters' => $operation['parameters'] ?? [],
                    'requestBody' => $operation['requestBody'] ?? null,
                    'responses' => array_keys($operation['responses'] ?? []),
                    'security' => $operation['security'] ?? null,
                ];
            }
        }

        return [
            'paths' => $paths,
            'schemas' => $decoded['components']['schemas'] ?? $decoded['definitions'] ?? [],
            'security' => $decoded['security'] ?? $decoded['securitySchemes'] ?? [],
        ];
    }

    private function normalizeForDisplay(string $body): string
    {
        $json = $this->decodeJson($body);

        if ($json !== null) {
            return json_encode(
                $json,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) ?: $body;
        }

        $newlineCount = substr_count($body, "\n");

        if ($newlineCount < 5 && strlen($body) > 200 && str_contains($body, '<')) {
            return preg_replace('/>\s*</', ">\n<", $body) ?? $body;
        }

        return $body;
    }

    private function decodeJson(string $body): mixed
    {
        if (trim($body) === '') {
            return null;
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }

    /**
     * @param  list<array{path: string, type: string, category: string, old: mixed, new: mixed}>  $changes
     */
    private function walkJsonDiff(mixed $old, mixed $new, string $path, array &$changes): void
    {
        if (is_array($old) && is_array($new)) {
            $this->walkArrayDiff($old, $new, $path, $changes);

            return;
        }

        if ($old === $new) {
            return;
        }

        $changes[] = [
            'path' => $path === '' ? '$' : $path,
            'type' => 'changed',
            'category' => $this->valueCategory($old, $new),
            'old' => $old,
            'new' => $new,
        ];
    }

    /**
     * @param  array<mixed>  $old
     * @param  array<mixed>  $new
     * @param  list<array{path: string, type: string, category: string, old: mixed, new: mixed}>  $changes
     */
    private function walkArrayDiff(array $old, array $new, string $path, array &$changes): void
    {
        $oldIsList = array_is_list($old);
        $newIsList = array_is_list($new);

        if ($oldIsList && $newIsList) {
            $this->walkListDiff($old, $new, $path, $changes);

            return;
        }

        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
        sort($keys);

        foreach ($keys as $key) {
            $childPath = $path === '' ? (string) $key : $path.'.'.$key;

            if (! array_key_exists($key, $old)) {
                $changes[] = ['path' => $childPath, 'type' => 'added', 'category' => 'schema', 'old' => null, 'new' => $new[$key]];
            } elseif (! array_key_exists($key, $new)) {
                $changes[] = ['path' => $childPath, 'type' => 'removed', 'category' => 'schema', 'old' => $old[$key], 'new' => null];
            } else {
                $this->walkJsonDiff($old[$key], $new[$key], $childPath, $changes);
            }
        }
    }

    /**
     * @param  list<mixed>  $old
     * @param  list<mixed>  $new
     * @param  list<array{path: string, type: string, category: string, old: mixed, new: mixed}>  $changes
     */
    private function walkListDiff(array $old, array $new, string $path, array &$changes): void
    {
        $max = max(count($old), count($new));

        for ($i = 0; $i < $max; $i++) {
            $childPath = $path === '' ? "[$i]" : $path."[$i]";

            if (! array_key_exists($i, $old)) {
                $changes[] = ['path' => $childPath, 'type' => 'added', 'category' => 'schema', 'old' => null, 'new' => $new[$i]];
            } elseif (! array_key_exists($i, $new)) {
                $changes[] = ['path' => $childPath, 'type' => 'removed', 'category' => 'schema', 'old' => $old[$i], 'new' => null];
            } else {
                $this->walkJsonDiff($old[$i], $new[$i], $childPath, $changes);
            }
        }
    }

    private function valueCategory(mixed $old, mixed $new): string
    {
        if ($this->jsonType($old) !== $this->jsonType($new)) {
            return 'schema';
        }

        return 'value';
    }

    private function jsonType(mixed $value): string
    {
        return match (true) {
            is_array($value) && array_is_list($value) => 'array',
            is_array($value) => 'object',
            is_bool($value) => 'boolean',
            is_int($value), is_float($value) => 'number',
            is_string($value) => 'string',
            $value === null => 'null',
            default => 'unknown',
        };
    }

    /**
     * @param  list<array{category: string}>  $changes
     */
    private function classify(array $changes): DiffClassification
    {
        $schema = $this->countCategory($changes, 'schema');
        $value = $this->countCategory($changes, 'value');

        if ($schema > 0 && $value > 0) {
            return DiffClassification::Mixed;
        }

        if ($schema > 0) {
            return DiffClassification::SchemaChange;
        }

        if ($value > 0) {
            return DiffClassification::ValueChange;
        }

        return DiffClassification::None;
    }

    /**
     * @param  list<array{category?: string}>  $changes
     */
    private function countCategory(array $changes, string $category): int
    {
        $count = 0;

        foreach ($changes as $change) {
            if (($change['category'] ?? 'value') === $category) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return list<string>
     */
    private function lineDiff(string $oldBody, string $newBody): array
    {
        $oldLines = preg_split("/\r\n|\n|\r/", $oldBody) ?: [];
        $newLines = preg_split("/\r\n|\n|\r/", $newBody) ?: [];
        $oldCount = count($oldLines);
        $newCount = count($newLines);

        if ($oldCount * $newCount > 250_000) {
            $diff = [];

            foreach ($oldLines as $line) {
                $diff[] = '- '.$line;
            }

            foreach ($newLines as $line) {
                $diff[] = '+ '.$line;
            }

            return $diff;
        }

        return $this->buildLcsDiff($oldLines, $newLines, $oldCount, $newCount);
    }

    /**
     * @param  list<string>  $oldLines
     * @param  list<string>  $newLines
     * @return list<string>
     */
    private function buildLcsDiff(array $oldLines, array $newLines, int $oldCount, int $newCount): array
    {
        $lcs = array_fill(0, $oldCount + 1, array_fill(0, $newCount + 1, 0));

        for ($i = $oldCount - 1; $i >= 0; $i--) {
            for ($j = $newCount - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $oldLines[$i] === $newLines[$j]
                    ? $lcs[$i + 1][$j + 1] + 1
                    : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        $diff = [];
        $i = 0;
        $j = 0;

        while ($i < $oldCount && $j < $newCount) {
            if ($oldLines[$i] === $newLines[$j]) {
                $diff[] = '  '.$oldLines[$i];
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $diff[] = '- '.$oldLines[$i];
                $i++;
            } else {
                $diff[] = '+ '.$newLines[$j];
                $j++;
            }
        }

        while ($i < $oldCount) {
            $diff[] = '- '.$oldLines[$i];
            $i++;
        }

        while ($j < $newCount) {
            $diff[] = '+ '.$newLines[$j];
            $j++;
        }

        return $diff;
    }
}
