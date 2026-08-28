<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ChangeIncidentStatus;
use App\Models\Address;
use App\Models\ChangeIncident;
use App\Models\Snapshot;

final class OpenChangeIncidentAction
{
    /**
     * @param  array<string, mixed>  $diff
     */
    public function execute(Address $address, Snapshot $snapshot, array $diff): ?ChangeIncident
    {
        if (($diff['is_first'] ?? false) || ! ($diff['has_changes'] ?? false)) {
            return null;
        }

        $open = ChangeIncident::query()
            ->where('address_id', $address->id)
            ->where('status', ChangeIncidentStatus::Open->value)
            ->first();

        if ($open !== null) {
            return $open;
        }

        return ChangeIncident::query()->create([
            'address_id' => $address->id,
            'opened_snapshot_id' => $snapshot->id,
            'status' => ChangeIncidentStatus::Open,
        ]);
    }
}
