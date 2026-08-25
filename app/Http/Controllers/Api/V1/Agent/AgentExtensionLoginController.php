<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Agent;

use App\Actions\ExtensionAgentLoginAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agent\StartExtensionAgentLoginRequest;
use App\Http\Resources\Api\V1\Agent\AgentExtensionLoginResource;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class AgentExtensionLoginController extends Controller
{
    public function store(
        StartExtensionAgentLoginRequest $request,
        ExtensionAgentLoginAction $login,
    ): JsonResponse {
        $hostname = $request->string('hostname')->toString();

        $ticket = $login->start(
            $request->string('name')->toString(),
            $hostname !== '' ? $hostname : null,
        );

        return response()->json(['ticket' => $ticket], 201);
    }

    public function show(string $ticket, ExtensionAgentLoginAction $login): AgentExtensionLoginResource
    {
        abort_unless($this->isTicket($ticket), 404);

        try {
            return new AgentExtensionLoginResource($login->consume($ticket));
        } catch (RuntimeException) {
            abort(404);
        }
    }

    private function isTicket(string $ticket): bool
    {
        return strlen($ticket) === 64 && ctype_xdigit($ticket);
    }
}
