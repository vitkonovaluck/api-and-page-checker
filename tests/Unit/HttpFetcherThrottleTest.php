<?php

namespace Tests\Unit;

use App\Services\HttpFetcher;
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
}
