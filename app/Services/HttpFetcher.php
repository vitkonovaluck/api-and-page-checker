<?php

namespace App\Services;

use GuzzleHttp\TransferStats;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class HttpFetcher
{
    /**
     * @param  array<string, string>  $headers
     */
    public function get(string $url, array $headers = []): FetchResult
    {
        $started = hrtime(true);
        /** @var array<string, int>|null $timing */
        $timing = null;

        try {
            $response = Http::timeout(30)
                ->withHeaders(array_merge([
                    'Accept' => 'application/json, text/plain, */*',
                    'User-Agent' => 'API-Snapshot-Checker/1.0',
                    'ngrok-skip-browser-warning' => 'true',
                ], $headers))
                ->withOptions([
                    'http_errors' => false,
                    'on_stats' => function (TransferStats $stats) use (&$timing): void {
                        $timing = $this->timingFromStats($stats);
                    },
                ])
                ->get($url);

            $elapsedMs = (int) round((hrtime(true) - $started) / 1_000_000);

            $headers = [];
            foreach ($response->headers() as $name => $values) {
                $headers[strtolower((string) $name)] = is_array($values)
                    ? implode(', ', $values)
                    : (string) $values;
            }

            return new FetchResult(
                statusCode: $response->status(),
                headers: $headers,
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
