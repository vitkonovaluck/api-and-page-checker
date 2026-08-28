<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AssertionOperator;
use App\Enums\AssertionType;
use App\Models\Address;
use App\Services\FetchResult;
use App\Services\JsonPathFilter;

final class EvaluateAssertionsAction
{
    /**
     * @return array{failed: bool, degraded: bool, results: list<array{type: string, passed: bool, expected: mixed, actual: mixed}>}
     */
    public function execute(Address $address, FetchResult $result): array
    {
        $results = [];
        $failed = false;
        $degraded = false;

        foreach ($address->assertions ?? [] as $assertion) {
            if (! is_array($assertion) || ! isset($assertion['type'])) {
                continue;
            }

            $evaluated = $this->evaluate($assertion, $result);
            $results[] = $evaluated;

            if ($evaluated['passed']) {
                continue;
            }

            $type = AssertionType::tryFrom((string) $assertion['type']);

            if ($type === AssertionType::MaxResponseMs || $type === AssertionType::MaxTtfbMs) {
                $degraded = true;

                continue;
            }

            $failed = true;
        }

        return [
            'failed' => $failed,
            'degraded' => $degraded,
            'results' => $results,
        ];
    }

    /**
     * @param  array<string, mixed>  $assertion
     * @return array{type: string, passed: bool, expected: mixed, actual: mixed}
     */
    private function evaluate(array $assertion, FetchResult $result): array
    {
        $type = AssertionType::tryFrom((string) $assertion['type']) ?? AssertionType::BodyContains;
        $expected = $assertion['value'] ?? $assertion['values'] ?? null;

        [$passed, $actual] = match ($type) {
            AssertionType::StatusIn => $this->statusIn($result->statusCode, $expected),
            AssertionType::MaxResponseMs => $this->maxMs($result->responseTimeMs, $expected),
            AssertionType::MaxTtfbMs => $this->maxMs($this->ttfb($result), $expected),
            AssertionType::JsonPath => $this->jsonPath($result->body, $assertion),
            AssertionType::HeaderContains => $this->headerContains($result->headers, $assertion),
            AssertionType::BodyContains => $this->bodyContains($result->body, $expected),
        };

        return [
            'type' => $type->value,
            'passed' => $passed,
            'expected' => $expected,
            'actual' => $actual,
        ];
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function statusIn(?int $status, mixed $expected): array
    {
        $allowed = is_array($expected) ? $expected : [$expected];
        $codes = array_map(intval(...), $allowed);

        return [in_array($status, $codes, true), $status];
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function maxMs(?int $actual, mixed $expected): array
    {
        $limit = (int) $expected;

        return [$actual !== null && $actual <= $limit, $actual];
    }

    /**
     * @param  array<string, mixed>  $assertion
     * @return array{0: bool, 1: mixed}
     */
    private function jsonPath(string $body, array $assertion): array
    {
        $decoded = json_decode($body, true);
        $path = (string) ($assertion['path'] ?? '$');
        $actual = is_array($decoded) ? JsonPathFilter::get($decoded, $path) : null;
        $operator = AssertionOperator::tryFrom((string) ($assertion['op'] ?? 'eq')) ?? AssertionOperator::Eq;
        $expected = $assertion['value'] ?? null;

        $passed = match ($operator) {
            AssertionOperator::Exists => $actual !== null,
            AssertionOperator::Eq => $actual == $expected,
            AssertionOperator::Neq => $actual != $expected,
            AssertionOperator::Contains => is_string($actual) && is_string($expected) && str_contains($actual, $expected),
        };

        return [$passed, $actual];
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $assertion
     * @return array{0: bool, 1: mixed}
     */
    private function headerContains(array $headers, array $assertion): array
    {
        $name = strtolower((string) ($assertion['header'] ?? $assertion['path'] ?? ''));
        $needle = (string) ($assertion['value'] ?? '');
        $actual = null;

        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === $name) {
                $actual = $value;
                break;
            }
        }

        return [is_string($actual) && str_contains(strtolower($actual), strtolower($needle)), $actual];
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function bodyContains(string $body, mixed $expected): array
    {
        $needle = (string) $expected;

        return [str_contains($body, $needle), strlen($body)];
    }

    private function ttfb(FetchResult $result): ?int
    {
        $value = $result->timing['ttfb_ms'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
