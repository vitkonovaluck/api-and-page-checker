<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\CheckAgent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCheckAgent
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($user === null || $token === null) {
            abort(401, __('agent.unauthenticated'));
        }

        $agent = CheckAgent::query()
            ->where('user_id', $user->id)
            ->where('personal_access_token_id', $token->id)
            ->first();

        if ($agent === null) {
            abort(403, __('agent.agent_required'));
        }

        $agent->forceFill([
            'last_seen_at' => now(),
            'last_ip' => $request->ip(),
        ])->save();

        $request->attributes->set('checkAgent', $agent);

        return $next($request);
    }
}
