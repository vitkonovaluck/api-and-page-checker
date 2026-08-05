<?php

namespace App\Livewire\Concerns;

trait NormalizesRequestHeaders
{
    /**
     * @param  array<int, array{name?: string|null, value?: string|null}>  $rows
     * @return array<string, string>|null
     */
    protected function normalizeRequestHeaders(array $rows): ?array
    {
        $headers = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $headers[$name] = (string) ($row['value'] ?? '');
        }

        return $headers === [] ? null : $headers;
    }

    /**
     * @param  array<string, string>|null  $headers
     * @return list<array{name: string, value: string}>
     */
    protected function headersToRows(?array $headers): array
    {
        $rows = [];

        foreach ($headers ?? [] as $name => $value) {
            $rows[] = ['name' => (string) $name, 'value' => (string) $value];
        }

        return $rows === [] ? [['name' => '', 'value' => '']] : $rows;
    }

    public function addHeaderRow(): void
    {
        $this->headers[] = ['name' => '', 'value' => ''];
    }

    public function removeHeaderRow(int $index): void
    {
        unset($this->headers[$index]);
        $this->headers = array_values($this->headers);

        if ($this->headers === []) {
            $this->headers = [['name' => '', 'value' => '']];
        }
    }
}
