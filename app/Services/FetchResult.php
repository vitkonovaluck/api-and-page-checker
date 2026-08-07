<?php

namespace App\Services;

readonly class FetchResult
{
    /**
     * @param  array<string, string>  $headers
     * @param  array{
     *     dns_ms: int,
     *     connect_ms: int,
     *     tls_ms: int,
     *     ttfb_ms: int,
     *     download_ms: int,
     *     total_ms: int
     * }|null  $timing
     */
    public function __construct(
        public ?int $statusCode,
        public array $headers,
        public string $body,
        public int $responseTimeMs,
        public ?string $errorMessage = null,
        public ?array $timing = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->errorMessage === null;
    }
}
