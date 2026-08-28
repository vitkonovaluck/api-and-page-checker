<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Address;
use App\Models\Snapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Snapshot>
 */
class SnapshotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = json_encode(['ok' => true], JSON_THROW_ON_ERROR);

        return [
            'address_id' => Address::factory(),
            'status_code' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => $body,
            'body_hash' => hash('sha256', $body),
            'response_time_ms' => fake()->numberBetween(10, 200),
            'timing' => [
                'dns_ms' => 5,
                'connect_ms' => 5,
                'tls_ms' => 5,
                'ttfb_ms' => 20,
                'download_ms' => 10,
                'total_ms' => 40,
            ],
            'error_message' => null,
            'assertion_failed' => false,
        ];
    }
}
