<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DiffChangeType;
use App\Models\Snapshot;

class DiffService
{
    /**
     * @var list<string>
     */
    private const LIST_IDENTITY_KEYS = ['id', 'slug', 'uuid', 'key', 'code'];

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
                $changes[] = ['key' => $key, 'type' => DiffChangeType::Added->value, 'old' => null, 'new' => $newValue];
            } elseif ($newValue === null) {
                $changes[] = ['key' => $key, 'type' => DiffChangeType::Removed->value, 'old' => $oldValue, 'new' => null];
            } else {
                $changes[] = ['key' => $key, 'type' => DiffChangeType::Changed->value, 'old' => $oldValue, 'new' => $newValue];
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
            if (array_is_list($old) && array_is_list($new)) {
                $this->diffJsonList($old, $new, $path, $changes);

                return;
            }

            $this->diffJsonObject($old, $new, $path, $changes);

            return;
        }

        if ($old !== $new) {
            $changes[] = [
                'path' => $path === '' ? '$' : $path,
                'type' => DiffChangeType::Changed->value,
                'old' => $old,
                'new' => $new,
            ];
        }
    }

    /**
     * @param  array<string|int, mixed>  $old
     * @param  array<string|int, mixed>  $new
     * @param  list<array{path: string, type: string, old: mixed, new: mixed}>  $changes
     */
    private function diffJsonObject(array $old, array $new, string $path, array &$changes): void
    {
        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
        sort($keys);

        foreach ($keys as $key) {
            $childPath = $path === '' ? (string) $key : $path.'.'.$key;

            if (! array_key_exists($key, $old)) {
                $changes[] = [
                    'path' => $childPath,
                    'type' => DiffChangeType::Added->value,
                    'old' => null,
                    'new' => $new[$key],
                ];

                continue;
            }

            if (! array_key_exists($key, $new)) {
                $changes[] = [
                    'path' => $childPath,
                    'type' => DiffChangeType::Removed->value,
                    'old' => $old[$key],
                    'new' => null,
                ];

                continue;
            }

            $this->walkJsonDiff($old[$key], $new[$key], $childPath, $changes);
        }
    }

    /**
     * @param  list<mixed>  $old
     * @param  list<mixed>  $new
     * @param  list<array{path: string, type: string, old: mixed, new: mixed}>  $changes
     */
    private function diffJsonList(array $old, array $new, string $path, array &$changes): void
    {
        $identityKey = $this->listIdentityKey($old, $new);

        if ($identityKey !== null) {
            $this->diffKeyedList(
                $this->indexByIdentity($old, $identityKey) ?? [],
                $this->indexByIdentity($new, $identityKey) ?? [],
                $this->identityValues($old, $identityKey),
                $this->identityValues($new, $identityKey),
                $path,
                $identityKey,
                $changes,
            );

            return;
        }

        if ($this->isUniqueScalarList($old) && $this->isUniqueScalarList($new)) {
            $this->diffKeyedList(
                $this->indexScalars($old),
                $this->indexScalars($new),
                $old,
                $new,
                $path,
                '',
                $changes,
            );

            return;
        }

        if ($this->isPureReorder($old, $new)) {
            $changes[] = [
                'path' => $this->jsonPath($path),
                'type' => DiffChangeType::Reordered->value,
                'old' => $old,
                'new' => $new,
            ];

            return;
        }

        $this->diffListByIndex($old, $new, $path, $changes);
    }

    /**
     * @param  array<string, mixed>  $oldById
     * @param  array<string, mixed>  $newById
     * @param  list<string|int>  $oldIds
     * @param  list<string|int>  $newIds
     * @param  list<array{path: string, type: string, old: mixed, new: mixed}>  $changes
     */
    private function diffKeyedList(
        array $oldById,
        array $newById,
        array $oldIds,
        array $newIds,
        string $path,
        string $key,
        array &$changes,
    ): void {
        $oldCommon = [];
        $newCommon = [];

        foreach ($oldIds as $id) {
            $idKey = (string) $id;

            if (! array_key_exists($idKey, $newById)) {
                $changes[] = [
                    'path' => $this->listItemPath($path, $key, $id),
                    'type' => DiffChangeType::Removed->value,
                    'old' => $oldById[$idKey],
                    'new' => null,
                ];

                continue;
            }

            $oldCommon[] = $id;
        }

        foreach ($newIds as $id) {
            $idKey = (string) $id;

            if (! array_key_exists($idKey, $oldById)) {
                $changes[] = [
                    'path' => $this->listItemPath($path, $key, $id),
                    'type' => DiffChangeType::Added->value,
                    'old' => null,
                    'new' => $newById[$idKey],
                ];

                continue;
            }

            $newCommon[] = $id;
        }

        if ($oldCommon !== $newCommon) {
            $changes[] = [
                'path' => $this->jsonPath($path),
                'type' => DiffChangeType::Reordered->value,
                'old' => $oldIds,
                'new' => $newIds,
            ];
        }

        foreach ($oldCommon as $id) {
            $idKey = (string) $id;

            $this->walkJsonDiff(
                $oldById[$idKey],
                $newById[$idKey],
                $this->listItemPath($path, $key, $id),
                $changes,
            );
        }
    }

    /**
     * @param  list<mixed>  $old
     * @param  list<mixed>  $new
     * @param  list<array{path: string, type: string, old: mixed, new: mixed}>  $changes
     */
    private function diffListByIndex(array $old, array $new, string $path, array &$changes): void
    {
        $max = max(count($old), count($new));

        for ($i = 0; $i < $max; $i++) {
            $childPath = $path === '' ? "[$i]" : $path."[$i]";

            if (! array_key_exists($i, $old)) {
                $changes[] = [
                    'path' => $childPath,
                    'type' => DiffChangeType::Added->value,
                    'old' => null,
                    'new' => $new[$i],
                ];

                continue;
            }

            if (! array_key_exists($i, $new)) {
                $changes[] = [
                    'path' => $childPath,
                    'type' => DiffChangeType::Removed->value,
                    'old' => $old[$i],
                    'new' => null,
                ];

                continue;
            }

            $this->walkJsonDiff($old[$i], $new[$i], $childPath, $changes);
        }
    }

    /**
     * @param  list<mixed>  $old
     * @param  list<mixed>  $new
     */
    private function listIdentityKey(array $old, array $new): ?string
    {
        foreach (self::LIST_IDENTITY_KEYS as $key) {
            if ($this->indexByIdentity($old, $key) !== null && $this->indexByIdentity($new, $key) !== null) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $items
     * @return array<string, array<string, mixed>>|null
     */
    private function indexByIdentity(array $items, string $key): ?array
    {
        $index = [];

        foreach ($items as $item) {
            if (! is_array($item) || ! array_key_exists($key, $item)) {
                return null;
            }

            $value = $item[$key];

            if ((! is_string($value) && ! is_int($value)) || $value === '') {
                return null;
            }

            $id = (string) $value;

            if (array_key_exists($id, $index)) {
                return null;
            }

            $index[$id] = $item;
        }

        return $index;
    }

    /**
     * @param  list<mixed>  $items
     * @return list<string|int>
     */
    private function identityValues(array $items, string $key): array
    {
        $values = [];

        foreach ($items as $item) {
            $values[] = $item[$key];
        }

        return $values;
    }

    /**
     * @param  list<mixed>  $items
     */
    private function isUniqueScalarList(array $items): bool
    {
        $seen = [];

        foreach ($items as $item) {
            if (! is_string($item) && ! is_int($item)) {
                return false;
            }

            $id = (string) $item;

            if ($id === '' || array_key_exists($id, $seen)) {
                return false;
            }

            $seen[$id] = true;
        }

        return true;
    }

    /**
     * @param  list<string|int>  $items
     * @return array<string, string|int>
     */
    private function indexScalars(array $items): array
    {
        $index = [];

        foreach ($items as $item) {
            $index[(string) $item] = $item;
        }

        return $index;
    }

    /**
     * @param  list<mixed>  $old
     * @param  list<mixed>  $new
     */
    private function isPureReorder(array $old, array $new): bool
    {
        if (count($old) !== count($new) || $old === $new) {
            return false;
        }

        $oldEncoded = array_map($this->encodeItem(...), $old);
        $newEncoded = array_map($this->encodeItem(...), $new);
        sort($oldEncoded);
        sort($newEncoded);

        return $oldEncoded === $newEncoded;
    }

    private function encodeItem(mixed $item): string
    {
        return json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: serialize($item);
    }

    private function jsonPath(string $path): string
    {
        return $path === '' ? '$' : $path;
    }

    private function listItemPath(string $path, string $key, string|int $identity): string
    {
        $segment = $key === ''
            ? '['.$identity.']'
            : '['.$key.'='.$identity.']';

        return $path === '' ? $segment : $path.$segment;
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
