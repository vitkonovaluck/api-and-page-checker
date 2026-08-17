<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\TransferStats;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Throwable;

class HttpFetcher
{
    private const TOO_MANY_REQUESTS = 429;

    private ?int $requestsPerMinute = null;

    private string $throttleKey = '';

    /**
     * @param  array<string, string>  $headers
     */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        ?int $requestsPerMinute = null,
        ?string $throttleKey = null,
    ): FetchResult {
        $this->requestsPerMinute = $requestsPerMinute;
        $this->throttleKey = $throttleKey ?? '';

        $maxAttempts = max(1, (int) config('checking.too_many_requests_retries', 3));
        $result = $this->attempt($method, $url, $headers, $body, throttle: true);

        for ($attempt = 1; $attempt < $maxAttempts; $attempt++) {
            if ($result->statusCode !== self::TOO_MANY_REQUESTS) {
                return $result;
            }

            $this->waitAfterTooManyRequests($url, $attempt, $result->headers);
            $result = $this->attempt($method, $url, $headers, $body, throttle: false);
        }

        return $result;
    }

    /**
     * @deprecated Use request() instead.
     *
     * @param  array<string, string>  $headers
     */
    public function get(string $url, array $headers = []): FetchResult
    {
        return $this->request('GET', $url, $headers);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function attempt(string $method, string $url, array $headers, ?string $body, bool $throttle): FetchResult
    {
        if ($throttle) {
            $this->throttleHost($url);
        }

        return $this->performRequest($method, $url, $headers, $body);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function performRequest(string $method, string $url, array $headers, ?string $body): FetchResult
    {
        $method = strtoupper($method);
        $started = hrtime(true);
        /** @var array<string, int>|null $timing */
        $timing = null;

        try {
            $response = $this->send($method, $url, $headers, $body, $timing);

            return $this->resultFromResponse($response, $started, $timing);
        } catch (ConnectionException|RequestException|Throwable $e) {
            return $this->resultFromException($e, $started, $timing);
        }
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, int>|null  $timing
     */
    private function send(string $method, string $url, array $headers, ?string $body, ?array &$timing): Response
    {
        $mergedHeaders = array_merge([
            'Accept' => 'application/json, text/plain, */*',
            'User-Agent' => 'API-Snapshot-Checker/1.0',
            'ngrok-skip-browser-warning' => 'true',
        ], $headers);

        $hasBody = $body !== null && $body !== '';
        if ($hasBody && ! $this->hasHeader($mergedHeaders, 'Content-Type')) {
            $mergedHeaders['Content-Type'] = $this->guessContentType($body);
        }

        $pending = Http::timeout(30)
            ->connectTimeout(10)
            ->withHeaders($mergedHeaders)
            ->withOptions([
                'http_errors' => false,
                'on_stats' => function (TransferStats $stats) use (&$timing): void {
                    $timing = $this->timingFromStats($stats);
                },
            ]);

        $options = [];
        if ($hasBody) {
            $options['body'] = $body;
        }

        return $pending->send($method, $url, $options);
    }

    /**
     * @param  array<string, int>|null  $timing
     */
    private function resultFromResponse(Response $response, int $started, ?array $timing): FetchResult
    {
        $responseHeaders = [];
        foreach ($response->headers() as $name => $values) {
            $responseHeaders[strtolower((string) $name)] = is_array($values)
                ? implode(', ', $values)
                : (string) $values;
        }

        return new FetchResult(
            statusCode: $response->status(),
            headers: $responseHeaders,
            body: $response->body(),
            responseTimeMs: $this->elapsedMs($started),
            timing: $timing,
        );
    }

    /**
     * @param  array<string, int>|null  $timing
     */
    private function resultFromException(Throwable $e, int $started, ?array $timing): FetchResult
    {
        return new FetchResult(
            statusCode: null,
            headers: [],
            body: '',
            responseTimeMs: $this->elapsedMs($started),
            errorMessage: $e->getMessage(),
            timing: $timing,
        );
    }

    private function elapsedMs(int $started): int
    {
        return max(0, (int) round((hrtime(true) - $started) / 1_000_000));
    }

    /**
     * Space outbound checks so a site stays within its checks-per-minute limit.
     */
    private function throttleHost(string $url): void
    {
        $maxPerMinute = $this->requestsPerMinute ?? (int) config('checking.requests_per_minute', 32);

        if ($maxPerMinute <= 0) {
            return;
        }

        $key = $this->activeThrottleKey($url);
        $lockSeconds = min(90, max(20, (int) ceil(60 / $maxPerMinute) + 15));
        $lock = Cache::lock('http-fetcher:lock:'.$key, $lockSeconds);

        $lock->block($lockSeconds, function () use ($key, $maxPerMinute): void {
            $this->waitForHostSlot($key, $maxPerMinute);
        });
    }

    private function waitForHostSlot(string $key, int $maxPerMinute): void
    {
        $penalty = max(1, (int) Cache::get($this->penaltyKey($key), 1));
        $minIntervalMs = (int) ceil(60_000 / $maxPerMinute) * $penalty;
        $cacheKey = 'http-fetcher:last-request:'.$key;

        $nowMs = (int) floor(microtime(true) * 1000);
        $lastMs = Cache::get($cacheKey);

        if ($lastMs !== null) {
            $waitMs = $minIntervalMs - ($nowMs - (int) $lastMs);
            // A future/stale cache value must not sleep for minutes (PHP 120s cap).
            $waitMs = min(max(0, $waitMs), $minIntervalMs);

            if ($waitMs > 0) {
                $this->sleepMs($waitMs);
                $nowMs = (int) floor(microtime(true) * 1000);
            }
        }

        Cache::put($cacheKey, $nowMs, 120);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function waitAfterTooManyRequests(string $url, int $attempt, array $headers): void
    {
        $this->rememberTooManyRequests($url);

        $backoffMs = max(1, (int) config('checking.too_many_requests_backoff_ms', 2000));
        $maxWaitMs = max(1, (int) config('checking.too_many_requests_max_wait_ms', 10_000));
        $fromHeader = $this->retryAfterMs($headers);
        $exponential = $backoffMs * (2 ** ($attempt - 1));
        $waitMs = min($fromHeader ?? $exponential, $maxWaitMs);

        $this->sleepMs($waitMs);
    }

    private function rememberTooManyRequests(string $url): void
    {
        $key = $this->penaltyKey($this->activeThrottleKey($url));
        $penalty = min(4, max(1, (int) Cache::get($key, 1)) * 2);

        Cache::put($key, $penalty, 120);
    }

    private function activeThrottleKey(string $url): string
    {
        if ($this->throttleKey !== '') {
            return $this->throttleKey;
        }

        return $this->hostFromUrl($url);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function retryAfterMs(array $headers): ?int
    {
        $raw = trim((string) ($headers['retry-after'] ?? ''));

        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            return (int) $raw * 1000;
        }

        $timestamp = strtotime($raw);

        if ($timestamp === false) {
            return null;
        }

        return max(0, ($timestamp - time()) * 1000);
    }

    private function penaltyKey(string $key): string
    {
        return 'http-fetcher:penalty:'.$key;
    }

    private function hostFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return 'unknown';
        }

        return strtolower($host);
    }

    private function sleepMs(int $waitMs): void
    {
        Sleep::for($waitMs)->milliseconds();
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function hasHeader(array $headers, string $name): bool
    {
        foreach (array_keys($headers) as $key) {
            if (strcasecmp((string) $key, $name) === 0) {
                return true;
            }
        }

        return false;
    }

    private function guessContentType(string $body): string
    {
        $trimmed = ltrim($body);

        if ($trimmed !== '' && (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '['))) {
            json_decode($trimmed);

            if (json_last_error() === JSON_ERROR_NONE) {
                return 'application/json';
            }
        }

        return 'text/plain';
    }

    /**
     * @return array{
     *     dns_ms: int,
     *     connect_ms: int,
     *     tls_ms: int,
     *     ttfb_ms: int,
     *     download_ms: int,
     *     total_ms: int
     * }|null
     */
    private function timingFromStats(TransferStats $stats): ?array
    {
        $handlerStats = $stats->getHandlerStats();

        if ($handlerStats === []) {
            return null;
        }

        $namelookup = (float) ($handlerStats['namelookup_time'] ?? 0);
        $connect = (float) ($handlerStats['connect_time'] ?? 0);
        $appconnect = (float) ($handlerStats['appconnect_time'] ?? 0);
        $starttransfer = (float) ($handlerStats['starttransfer_time'] ?? 0);
        $total = (float) ($handlerStats['total_time'] ?? $stats->getTransferTime() ?? 0);

        $afterConnect = $appconnect > 0 ? $appconnect : $connect;

        return [
            'dns_ms' => $this->secondsToMs($namelookup),
            'connect_ms' => $this->secondsToMs(max(0, $connect - $namelookup)),
            'tls_ms' => $this->secondsToMs(max(0, $appconnect - $connect)),
            'ttfb_ms' => $this->secondsToMs(max(0, $starttransfer - $afterConnect)),
            'download_ms' => $this->secondsToMs(max(0, $total - $starttransfer)),
            'total_ms' => $this->secondsToMs($total),
        ];
    }

    private function secondsToMs(float $seconds): int
    {
        return (int) round($seconds * 1000);
    }
}
