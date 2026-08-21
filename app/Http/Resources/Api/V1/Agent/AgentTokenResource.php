<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Agent;

use App\Models\CheckAgent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AgentTokenResource extends JsonResource
{
    public static $wrap = null;

    public function __construct(
        private readonly CheckAgent $agent,
        private readonly User $user,
        private readonly string $plainTextToken,
    ) {
        parent::__construct($agent);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->plainTextToken,
            'token_type' => 'Bearer',
            'agent' => new AgentResource($this->agent),
            'user' => new AgentUserResource($this->user),
        ];
    }
}
