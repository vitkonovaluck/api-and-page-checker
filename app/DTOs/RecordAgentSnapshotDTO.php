<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Http\Requests\Api\V1\Agent\StoreAgentSnapshotRequest;

final readonly class RecordAgentSnapshotDTO
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>|null  $timing
     */
    public function __construct(
        public int $addressId,
        public ?int $statusCode,
        public array $headers,
        public string $body,
        public int $responseTimeMs,
        public ?array $timing,
        public ?string $errorMessage,
    ) {}

    public static function fromRequest(StoreAgentSnapshotRequest $request): self
    {
        $headers = $request->input('headers', []);
        $timing = $request->input('timing');
        $error = $request->string('error_message')->toString();

        return new self(
            addressId: $request->integer('address_id'),
            statusCode: $request->filled('status_code') ? $request->integer('status_code') : null,
            headers: is_array($headers) ? self::stringifyHeaders($headers) : [],
            body: $request->string('body')->toString(),
            responseTimeMs: $request->integer('response_time_ms'),
            timing: is_array($timing) ? $timing : null,
            errorMessage: $error !== '' ? $error : null,
        );
    }

    /**
     * @param  array<mixed, mixed>  $headers
     * @return array<string, string>
     */
    private static function stringifyHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            $normalized[$name] = is_scalar($value) ? (string) $value : '';
        }

        return $normalized;
    }
}
