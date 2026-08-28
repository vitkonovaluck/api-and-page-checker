<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\IssueAgentTokenDTO;
use App\Models\CheckAgent;
use App\Models\User;

final class IssueAgentTokenAction
{
    /**
     * @return array{agent: CheckAgent, plainTextToken: string}
     */
    public function execute(User $user, IssueAgentTokenDTO $dto): array
    {
        $agent = $this->findOrCreateAgent($user, $dto);
        $this->revokeExistingToken($user, $agent);

        $token = $user->createToken('agent:'.$agent->id, ['agent']);

        $agent->forceFill([
            'hostname' => $dto->hostname ?? $agent->hostname,
            'region' => $dto->region ?? $agent->region,
            'personal_access_token_id' => $token->accessToken->id,
            'last_seen_at' => now(),
            'last_ip' => $dto->ip,
        ])->save();

        return [
            'agent' => $agent,
            'plainTextToken' => $token->plainTextToken,
        ];
    }

    private function findOrCreateAgent(User $user, IssueAgentTokenDTO $dto): CheckAgent
    {
        return CheckAgent::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'name' => $dto->name,
            ],
            [
                'hostname' => $dto->hostname,
                'region' => $dto->region,
            ],
        );
    }

    private function revokeExistingToken(User $user, CheckAgent $agent): void
    {
        if ($agent->personal_access_token_id === null) {
            return;
        }

        $user->tokens()->whereKey($agent->personal_access_token_id)->delete();
        $agent->forceFill(['personal_access_token_id' => null])->save();
    }
}
