<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChangeIncidentStatus;
use App\Models\Address;
use App\Models\ChangeIncident;
use App\Models\Snapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChangeIncident>
 */
class ChangeIncidentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'address_id' => Address::factory(),
            'opened_snapshot_id' => Snapshot::factory(),
            'status' => ChangeIncidentStatus::Open,
        ];
    }
}
