<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Agent;

use App\Actions\StartAgentCheckRunAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agent\StartAgentCheckRunRequest;
use App\Http\Resources\Api\V1\Agent\AgentCheckRunResource;
use App\Models\CheckAgent;
use App\Models\Site;

final class AgentCheckRunController extends Controller
{
    public function store(StartAgentCheckRunRequest $request, StartAgentCheckRunAction $start): AgentCheckRunResource
    {
        $agent = $request->attributes->get('checkAgent');
        assert($agent instanceof CheckAgent);

        $site = Site::query()->findOrFail($request->integer('site_id'));
        $this->authorize('update', $site);

        $run = $start->execute($agent, $site, $request->addressIds());

        return new AgentCheckRunResource($run);
    }
}
