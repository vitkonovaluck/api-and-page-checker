<?php

namespace Tests\Unit;

use App\Services\HttpFetcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HttpFetcherThrottleTest extends TestCase
{
    public function test_spaces_requests_to_same_host_by_configured_rate(): void
    {
        config(['checking.requests_per_minute' => 120]);

        Http::fake([
            'https://api.example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $fetcher = new HttpFetcher;

        $started = hrtime(true);
        $fetcher->request('GET', 'https://api.example.com/one');
        $fetcher->request('GET', 'https://api.example.com/two');
        $elapsedMs = (int) round((hrtime(true) - $started) / 1_000_000);

        // 120/min ⇒ ≥500ms between requests
        $this->assertGreaterThanOrEqual(450, $elapsedMs);
        Http::assertSentCount(2);
    }

    public function test_does_not_throttle_when_limit_is_zero(): void
    {
        config(['checking.requests_per_minute' => 0]);

        Http::fake([
            'https://api.example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $fetcher = new HttpFetcher;

        $started = hrtime(true);
        $fetcher->request('GET', 'https://api.example.com/one');
        $fetcher->request('GET', 'https://api.example.com/two');
        $elapsedMs = (int) round((hrtime(true) - $started) / 1_000_000);

        $this->assertLessThan(200, $elapsedMs);
        Http::assertSentCount(2);
    }

    public function test_does_not_sleep_longer_than_one_interval_when_cache_is_ahead(): void
    {
        config(['checking.requests_per_minute' => 60]);

        Http::fake([
            'https://api.example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $nowMs = (int) floor(microtime(true) * 1000);
        Cache::put('http-fetcher:last-request:api.example.com', $nowMs + 120_000, 120);

        $fetcher = new HttpFetcher;
        $started = hrtime(true);
        $fetcher->request('GET', 'https://api.example.com/one');
        $elapsedMs = (int) round((hrtime(true) - $started) / 1_000_000);

        $this->assertLessThan(2500, $elapsedMs);
        Http::assertSentCount(1);
    }
}
