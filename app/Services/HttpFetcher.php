<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class HttpFetcher
{
    public function get(string $url): FetchResult
    {
        $started = hrtime(true);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json, text/plain, */*',
                    'User-Agent' => 'API-Snapshot-Checker/1.0',
                    'ngrok-skip-browser-warning' => 'true',
                ])
                ->withOptions(['http_errors' => false])
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
            );
        } catch (ConnectionException|RequestException|Throwable $e) {
            $elapsedMs = (int) round((hrtime(true) - $started) / 1_000_000);

            return new FetchResult(
                statusCode: null,
                headers: [],
                body: '',
                responseTimeMs: max(0, $elapsedMs),
                errorMessage: $e->getMessage(),
            );
        }
    }
}
