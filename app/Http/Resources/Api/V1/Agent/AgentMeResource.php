<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Agent;

use App\Models\CheckAgent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AgentMeResource extends JsonResource
{
    public static $wrap = null;

    public function __construct(
        private readonly User $user,
        private readonly CheckAgent $agent,
    ) {
        parent::__construct($agent);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => new AgentUserResource($this->user),
            'agent' => new AgentResource($this->agent),
        ];
    }
}
