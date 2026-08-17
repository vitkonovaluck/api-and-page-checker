<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\HttpFetcher;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class HttpFetcherThrottleTest extends TestCase
{
    public function test_spaces_requests_to_same_host_by_configured_rate(): void
    {
        // Arrange
        Sleep::fake();
        config(['checking.requests_per_minute' => 120]);

        Http::fake([
            'https://api.example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $fetcher = new HttpFetcher;

        // Act
        $fetcher->request('GET', 'https://api.example.com/one');
        $fetcher->request('GET', 'https://api.example.com/two');

        // Assert
        Sleep::assertSleptTimes(1);
        Http::assertSentCount(2);
    }

    public function test_does_not_throttle_when_limit_is_zero(): void
    {
        // Arrange
        Sleep::fake();
        config(['checking.requests_per_minute' => 0]);

        Http::fake([
            'https://api.example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $fetcher = new HttpFetcher;

        // Act
        $fetcher->request('GET', 'https://api.example.com/one');
        $fetcher->request('GET', 'https://api.example.com/two');

        // Assert
        Sleep::assertNeverSlept();
        Http::assertSentCount(2);
    }

    public function test_does_not_sleep_longer_than_one_interval_when_cache_is_ahead(): void
    {
        // Arrange
        Sleep::fake();
        config(['checking.requests_per_minute' => 60]);

        Http::fake([
            'https://api.example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $nowMs = (int) floor(microtime(true) * 1000);
        Cache::put('http-fetcher:last-request:api.example.com', $nowMs + 120_000, 120);

        $fetcher = new HttpFetcher;

        // Act
        $fetcher->request('GET', 'https://api.example.com/one');

        // Assert
        Sleep::assertSleptTimes(1);
        Sleep::assertSlept(fn (CarbonInterval $duration) => (int) round($duration->totalMilliseconds) === 1000);
        Http::assertSentCount(1);
    }

    public function test_uses_explicit_requests_per_minute_when_config_disables_throttle(): void
    {
        // Arrange
        Sleep::fake();
        config(['checking.requests_per_minute' => 0]);

        Http::fake([
            'https://api.example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $fetcher = new HttpFetcher;

        // Act
        $fetcher->request('GET', 'https://api.example.com/one', [], null, 120, 'site-1');
        $fetcher->request('GET', 'https://api.example.com/two', [], null, 120, 'site-1');

        // Assert
        Sleep::assertSleptTimes(1);
        Http::assertSentCount(2);
    }
}
