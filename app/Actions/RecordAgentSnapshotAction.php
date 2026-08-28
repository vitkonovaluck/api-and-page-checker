<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\DiffOptionsDTO;
use App\DTOs\RecordAgentSnapshotDTO;
use App\Enums\CheckOutcome;
use App\Events\SnapshotRecorded;
use App\Models\Address;
use App\Models\CheckAgent;
use App\Models\CheckRun;
use App\Models\Snapshot;
use App\Services\DiffService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class RecordAgentSnapshotAction
{
    public function __construct(private DiffService $diffService) {}

    public function execute(CheckAgent $agent, CheckRun $run, RecordAgentSnapshotDTO $dto): Snapshot
    {
        $run->loadMissing('site');
        $this->assertRunAllowsAgent($agent, $run);
        $address = $this->addressForRun($run, $dto->addressId);
        $this->assertNotDuplicate($run, $address);

        return DB::transaction(function () use ($agent, $run, $address, $dto): Snapshot {
            $previous = $address->snapshots()->orderByDesc('id')->first();
            $address->loadMissing('baselineSnapshot');

            $snapshot = Snapshot::query()->create([
                'address_id' => $address->id,
                'check_run_id' => $run->id,
                'check_agent_id' => $agent->id,
                'status_code' => $dto->statusCode,
                'headers' => $dto->headers,
                'body' => $dto->body,
                'body_hash' => hash('sha256', $dto->body),
                'response_time_ms' => $dto->responseTimeMs,
                'timing' => $dto->timing,
                'error_message' => $dto->errorMessage,
            ]);

            $options = DiffOptionsDTO::fromAddress($address);
            $historyDiff = $this->diffService->compare($previous, $snapshot, $options);
            $compareTo = $address->baselineSnapshot ?? $previous;
            $alertDiff = $compareTo !== null && $previous !== null && $compareTo->id !== $previous->id
                ? $this->diffService->compare($compareTo, $snapshot, $options)
                : $historyDiff;

            $outcome = match (true) {
                $dto->errorMessage !== null => CheckOutcome::Failed,
                ($alertDiff['has_changes'] ?? false) && ! ($alertDiff['is_first'] ?? false) => CheckOutcome::Changed,
                default => CheckOutcome::Ok,
            };

            $snapshot->forceFill(['check_outcome' => $outcome])->save();
            $address->forceFill(['last_checked_at' => now()])->save();
            $this->decrementRemainingJobs($run);

            SnapshotRecorded::dispatch($snapshot, $alertDiff, CheckRun::SOURCE_AGENT);

            return $snapshot;
        });
    }

    private function assertRunAllowsAgent(CheckAgent $agent, CheckRun $run): void
    {
        if ($run->source !== CheckRun::SOURCE_AGENT) {
            throw new HttpException(403, __('agent.run_not_agent'));
        }

        if ($run->site?->user_id !== $agent->user_id) {
            throw new HttpException(403, __('agent.run_forbidden'));
        }

        if ($run->check_agent_id !== null && $run->check_agent_id !== $agent->id) {
            throw new HttpException(403, __('agent.run_forbidden'));
        }
    }

    private function addressForRun(CheckRun $run, int $addressId): Address
    {
        $address = Address::query()
            ->whereKey($addressId)
            ->where('site_id', $run->site_id)
            ->first();

        if ($address === null) {
            throw new HttpException(404, __('agent.address_not_in_run'));
        }

        return $address;
    }

    private function assertNotDuplicate(CheckRun $run, Address $address): void
    {
        $exists = Snapshot::query()
            ->where('check_run_id', $run->id)
            ->where('address_id', $address->id)
            ->exists();

        if ($exists) {
            throw new HttpException(409, __('agent.snapshot_duplicate'));
        }
    }

    private function decrementRemainingJobs(CheckRun $run): void
    {
        CheckRun::query()
            ->whereKey($run->id)
            ->where('remaining_jobs', '>', 0)
            ->decrement('remaining_jobs');
    }
}
