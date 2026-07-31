<?php

namespace App\Services;

use App\Models\Snapshot;

class DiffService
{
    /**
     * @return array{
     *     has_changes: bool,
     *     is_first: bool,
     *     status_code: array{old: ?int, new: ?int, changed: bool},
     *     response_time_ms: array{old: ?int, new: int, delta: ?int},
     *     headers: list<array{key: string, type: string, old: ?string, new: ?string}>,
     *     body: array{
     *         type: string,
     *         changed: bool,
     *         changes: list<array{path: string, type: string, old: mixed, new: mixed}>,
     *         text_diff: list<string>
     *     },
     *     error_message: array{old: ?string, new: ?string, changed: bool}
     * }
     */
    public function compare(?Snapshot $previous, Snapshot $current): array
    {
        if ($previous === null) {
            return [
                'has_changes' => true,
                'is_first' => true,
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

        $statusChanged = $previous->status_code !== $current->status_code;
        $errorChanged = $previous->error_message !== $current->error_message;
        $headerChanges = $this->diffHeaders(
            $previous->headers ?? [],
            $current->headers ?? [],
        );
        $bodyDiff = $this->diffBody($previous->body ?? '', $current->body ?? '');

        $hasChanges = $statusChanged
            || $errorChanged
            || $headerChanges !== []
            || $bodyDiff['changed'];

        return [
            'has_changes' => $hasChanges,
            'is_first' => false,
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
     * @param  array<string, string>  $old
     * @param  array<string, string>  $new
     * @return list<array{key: string, type: string, old: ?string, new: ?string}>
     */
    private function diffHeaders(array $old, array $new): array
    {
        $changes = [];
        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
        sort($keys);

        foreach ($keys as $key) {
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
     *     changes: list<array{path: string, type: string, old: mixed, new: mixed}>,
     *     text_diff: list<string>,
     *     old_preview: string,
     *     new_preview: string
     * }
     */
    private function diffBody(string $oldBody, string $newBody): array
    {
        $oldPreview = $this->normalizeForDisplay($oldBody);
        $newPreview = $this->normalizeForDisplay($newBody);

        if (hash('sha256', $oldBody) === hash('sha256', $newBody)) {
            return [
                'type' => 'none',
                'changed' => false,
                'changes' => [],
                'text_diff' => [],
                'old_preview' => $oldPreview,
                'new_preview' => $newPreview,
            ];
        }

        $oldJson = $this->decodeJson($oldBody);
        $newJson = $this->decodeJson($newBody);

        if ($oldJson !== null && $newJson !== null) {
            $changes = [];
            $this->walkJsonDiff($oldJson, $newJson, '', $changes);

            return [
                'type' => 'json',
                'changed' => $changes !== [],
                'changes' => $changes,
                'text_diff' => $changes !== []
                    ? $this->lineDiff($oldPreview, $newPreview)
                    : [],
                'old_preview' => $oldPreview,
                'new_preview' => $newPreview,
            ];
        }

        return [
            'type' => 'text',
            'changed' => true,
            'changes' => [],
            'text_diff' => $this->lineDiff($oldPreview, $newPreview),
            'old_preview' => $oldPreview,
            'new_preview' => $newPreview,
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
     * @param  list<array{path: string, type: string, old: mixed, new: mixed}>  $changes
     */
    private function walkJsonDiff(mixed $old, mixed $new, string $path, array &$changes): void
    {
        if (is_array($old) && is_array($new)) {
            $oldIsList = array_is_list($old);
            $newIsList = array_is_list($new);

            if ($oldIsList && $newIsList) {
                $max = max(count($old), count($new));
                for ($i = 0; $i < $max; $i++) {
                    $childPath = $path === '' ? "[$i]" : $path."[$i]";
                    if (! array_key_exists($i, $old)) {
                        $changes[] = ['path' => $childPath, 'type' => 'added', 'old' => null, 'new' => $new[$i]];
                    } elseif (! array_key_exists($i, $new)) {
                        $changes[] = ['path' => $childPath, 'type' => 'removed', 'old' => $old[$i], 'new' => null];
                    } else {
                        $this->walkJsonDiff($old[$i], $new[$i], $childPath, $changes);
                    }
                }

                return;
            }

            $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
            sort($keys);

            foreach ($keys as $key) {
                $childPath = $path === '' ? (string) $key : $path.'.'.$key;
                if (! array_key_exists($key, $old)) {
                    $changes[] = ['path' => $childPath, 'type' => 'added', 'old' => null, 'new' => $new[$key]];
                } elseif (! array_key_exists($key, $new)) {
                    $changes[] = ['path' => $childPath, 'type' => 'removed', 'old' => $old[$key], 'new' => null];
                } else {
                    $this->walkJsonDiff($old[$key], $new[$key], $childPath, $changes);
                }
            }

            return;
        }

        if ($old !== $new) {
            $changes[] = [
                'path' => $path === '' ? '$' : $path,
                'type' => 'changed',
                'old' => $old,
                'new' => $new,
            ];
        }
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

        // Avoid O(n*m) blow-ups on huge HTML documents.
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

        $lcs = array_fill(0, $oldCount + 1, array_fill(0, $newCount + 1, 0));

        for ($i = $oldCount - 1; $i >= 0; $i--) {
            for ($j = $newCount - 1; $j >= 0; $j--) {
                if ($oldLines[$i] === $newLines[$j]) {
                    $lcs[$i][$j] = $lcs[$i + 1][$j + 1] + 1;
                } else {
                    $lcs[$i][$j] = max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
                }
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
