<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Agent;

use App\Models\CheckRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CheckRun
 */
final class AgentCheckRunResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'check_agent_id' => $this->check_agent_id,
            'source' => $this->source,
            'remaining_jobs' => $this->remaining_jobs,
            'started_at' => $this->started_at?->toISOString(),
        ];
    }
}
