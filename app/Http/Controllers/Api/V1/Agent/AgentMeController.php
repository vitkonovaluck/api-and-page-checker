<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Agent\AgentMeResource;
use App\Models\CheckAgent;
use App\Models\User;
use Illuminate\Http\Request;

final class AgentMeController extends Controller
{
    public function show(Request $request): AgentMeResource
    {
        $user = $request->user();
        $agent = $request->attributes->get('checkAgent');
        assert($user instanceof User);
        assert($agent instanceof CheckAgent);

        return new AgentMeResource($user, $agent);
    }
}
