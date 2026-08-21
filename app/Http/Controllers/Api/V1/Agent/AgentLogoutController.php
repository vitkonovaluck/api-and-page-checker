<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Controllers\Controller;
use App\Models\CheckAgent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AgentLogoutController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $agent = $request->attributes->get('checkAgent');
        assert($user instanceof User);
        assert($agent instanceof CheckAgent);

        $tokenId = $agent->personal_access_token_id;
        $agent->forceFill(['personal_access_token_id' => null])->save();

        if ($tokenId !== null) {
            $user->tokens()->whereKey($tokenId)->delete();
        }

        return response()->json(['message' => __('agent.logged_out')]);
    }
}
