<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\EvaluateAlertRulesAction;
use App\Actions\OpenChangeIncidentAction;
use App\Events\SnapshotRecorded;

final class HandleSnapshotRecorded
{
    public function __construct(
        private OpenChangeIncidentAction $openIncident,
        private EvaluateAlertRulesAction $evaluateAlerts,
    ) {}

    public function handle(SnapshotRecorded $event): void
    {
        $snapshot = $event->snapshot->loadMissing('address');
        $address = $snapshot->address;

        if ($address === null) {
            return;
        }

        $this->openIncident->execute($address, $snapshot, $event->diff);
        $this->evaluateAlerts->execute($address, $snapshot, $event->diff, $event->source);
    }
}
