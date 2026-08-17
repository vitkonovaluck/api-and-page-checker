<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\HttpFetcher;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class HttpFetcherTooManyRequestsTest extends TestCase
{
    public function test_retries_too_many_requests_and_returns_successful_response(): void
    {
        // Arrange
        Sleep::fake();
        config([
            'checking.requests_per_minute' => 0,
            'checking.too_many_requests_retries' => 3,
            'checking.too_many_requests_backoff_ms' => 2000,
        ]);

        Http::fake([
            'https://api.example.com/page' => Http::sequence()
                ->push('Too Many Requests', 429)
                ->push(['ok' => true], 200),
        ]);

        $fetcher = new HttpFetcher;

        // Act
        $result = $fetcher->request('GET', 'https://api.example.com/page');

        // Assert
        $this->assertSame(200, $result->statusCode);
        Http::assertSentCount(2);
        Sleep::assertSleptTimes(1);
        Sleep::assertSlept(fn (CarbonInterval $duration) => (int) round($duration->totalMilliseconds) === 2000);
    }

    public function test_returns_too_many_requests_after_retries_are_exhausted(): void
    {
        // Arrange
        Sleep::fake();
        config([
            'checking.requests_per_minute' => 0,
            'checking.too_many_requests_retries' => 2,
            'checking.too_many_requests_backoff_ms' => 1000,
        ]);

        Http::fake([
            'https://api.example.com/page' => Http::sequence()
                ->push('Too Many Requests', 429)
                ->push('Too Many Requests', 429),
        ]);

        $fetcher = new HttpFetcher;

        // Act
        $result = $fetcher->request('GET', 'https://api.example.com/page');

        // Assert
        $this->assertSame(429, $result->statusCode);
        Http::assertSentCount(2);
    }

    public function test_does_not_retry_too_many_requests_when_retries_is_one(): void
    {
        // Arrange
        Sleep::fake();
        config([
            'checking.requests_per_minute' => 0,
            'checking.too_many_requests_retries' => 1,
        ]);

        Http::fake([
            'https://api.example.com/page' => Http::response('Too Many Requests', 429),
        ]);

        $fetcher = new HttpFetcher;

        // Act
        $result = $fetcher->request('GET', 'https://api.example.com/page');

        // Assert
        $this->assertSame(429, $result->statusCode);
        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
    }

    public function test_uses_retry_after_header_capped_by_max_wait(): void
    {
        // Arrange
        Sleep::fake();
        config([
            'checking.requests_per_minute' => 0,
            'checking.too_many_requests_retries' => 2,
            'checking.too_many_requests_max_wait_ms' => 3000,
        ]);

        Http::fake([
            'https://api.example.com/page' => Http::sequence()
                ->push('Too Many Requests', 429, ['Retry-After' => '30'])
                ->push(['ok' => true], 200),
        ]);

        $fetcher = new HttpFetcher;

        // Act
        $result = $fetcher->request('GET', 'https://api.example.com/page');

        // Assert
        $this->assertSame(200, $result->statusCode);
        Sleep::assertSleptTimes(1);
        Sleep::assertSlept(fn (CarbonInterval $duration) => (int) round($duration->totalMilliseconds) === 3000);
    }

    public function test_slows_later_requests_to_the_same_host_after_too_many_requests(): void
    {
        // Arrange
        Sleep::fake();
        config([
            'checking.requests_per_minute' => 120,
            'checking.too_many_requests_retries' => 2,
            'checking.too_many_requests_backoff_ms' => 100,
        ]);

        Http::fake([
            'https://api.example.com/page' => Http::sequence()
                ->push('Too Many Requests', 429)
                ->push(['ok' => true], 200),
            'https://api.example.com/other' => Http::response(['ok' => true], 200),
        ]);

        $fetcher = new HttpFetcher;

        // Act
        $fetcher->request('GET', 'https://api.example.com/page');
        $fetcher->request('GET', 'https://api.example.com/other');

        // Assert
        Sleep::assertSleptTimes(2);
        Sleep::assertSlept(fn (CarbonInterval $duration) => (int) round($duration->totalMilliseconds) === 100);
        Sleep::assertSlept(fn (CarbonInterval $duration) => (int) round($duration->totalMilliseconds) >= 900);
    }
}
