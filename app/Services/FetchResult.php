<?php

namespace App\Services;

readonly class FetchResult
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public ?int $statusCode,
        public array $headers,
        public string $body,
        public int $responseTimeMs,
        public ?string $errorMessage = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->errorMessage === null;
    }
}
