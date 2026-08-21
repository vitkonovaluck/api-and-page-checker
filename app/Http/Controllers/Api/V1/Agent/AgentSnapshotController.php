<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Agent;

use App\Actions\RecordAgentSnapshotAction;
use App\DTOs\RecordAgentSnapshotDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agent\StoreAgentSnapshotRequest;
use App\Http\Resources\Api\V1\Agent\AgentSnapshotResource;
use App\Models\CheckAgent;
use App\Models\CheckRun;

final class AgentSnapshotController extends Controller
{
    public function store(
        StoreAgentSnapshotRequest $request,
        CheckRun $checkRun,
        RecordAgentSnapshotAction $record,
    ): AgentSnapshotResource {
        $agent = $request->attributes->get('checkAgent');
        assert($agent instanceof CheckAgent);

        $snapshot = $record->execute(
            $agent,
            $checkRun,
            RecordAgentSnapshotDTO::fromRequest($request),
        );

        return new AgentSnapshotResource($snapshot);
    }
}
