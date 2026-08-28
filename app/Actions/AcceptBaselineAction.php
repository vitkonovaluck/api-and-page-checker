<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ChangeIncidentStatus;
use App\Models\Address;
use App\Models\ChangeIncident;
use App\Models\Snapshot;
use App\Models\User;

final class AcceptBaselineAction
{
    public function execute(Address $address, Snapshot $snapshot, User $user): Address
    {
        $address->forceFill([
            'baseline_snapshot_id' => $snapshot->id,
        ])->save();

        ChangeIncident::query()
            ->where('address_id', $address->id)
            ->where('status', ChangeIncidentStatus::Open->value)
            ->update([
                'status' => ChangeIncidentStatus::Accepted->value,
                'closed_snapshot_id' => $snapshot->id,
                'accepted_at' => now(),
                'accepted_by' => $user->id,
            ]);

        return $address->refresh();
    }
}
