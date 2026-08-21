<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Agent;

use App\Actions\LoginAgentAction;
use App\DTOs\IssueAgentTokenDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agent\LoginAgentRequest;
use App\Http\Resources\Api\V1\Agent\AgentTokenResource;

final class AgentLoginController extends Controller
{
    public function store(LoginAgentRequest $request, LoginAgentAction $login): AgentTokenResource
    {
        $result = $login->execute(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            new IssueAgentTokenDTO(
                name: $request->string('name')->toString(),
                hostname: $request->string('hostname')->toString() ?: null,
                ip: $request->ip(),
            ),
        );

        return new AgentTokenResource(
            $result['agent'],
            $result['user'],
            $result['plainTextToken'],
        );
    }
}
