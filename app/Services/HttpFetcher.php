<?php

namespace App\Services;

use GuzzleHttp\TransferStats;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class HttpFetcher
{
    /**
     * @param  array<string, string>  $headers
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null): FetchResult
    {
        $this->throttleHost($url);

        $method = strtoupper($method);
        $started = hrtime(true);
        /** @var array<string, int>|null $timing */
        $timing = null;

        try {
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

            $response = $pending->send($method, $url, $options);

            $elapsedMs = (int) round((hrtime(true) - $started) / 1_000_000);

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
                responseTimeMs: max(0, $elapsedMs),
                timing: $timing,
            );
        } catch (ConnectionException|RequestException|Throwable $e) {
            $elapsedMs = (int) round((hrtime(true) - $started) / 1_000_000);

            return new FetchResult(
                statusCode: null,
                headers: [],
                body: '',
                responseTimeMs: max(0, $elapsedMs),
                errorMessage: $e->getMessage(),
                timing: $timing,
            );
        }
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
     * Space outbound checks so a single host stays within requests_per_minute.
     */
    private function throttleHost(string $url): void
    {
        $maxPerMinute = (int) config('checking.requests_per_minute', 32);

        if ($maxPerMinute <= 0) {
            return;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            $host = 'unknown';
        }

        $host = strtolower($host);
        $key = 'http-fetcher:last-request:'.$host;
        $minIntervalMs = (int) ceil(60_000 / $maxPerMinute);

        $nowMs = (int) floor(microtime(true) * 1000);
        $lastMs = Cache::get($key);

        if ($lastMs !== null) {
            $waitMs = $minIntervalMs - ($nowMs - (int) $lastMs);

            if ($waitMs > 0) {
                usleep($waitMs * 1000);
                $nowMs = (int) floor(microtime(true) * 1000);
            }
        }

        Cache::put($key, $nowMs, 120);
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
