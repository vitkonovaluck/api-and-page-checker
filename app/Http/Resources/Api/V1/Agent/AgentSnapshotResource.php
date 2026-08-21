<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Agent;

use App\Models\Snapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Snapshot
 */
final class AgentSnapshotResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'address_id' => $this->address_id,
            'check_run_id' => $this->check_run_id,
            'check_agent_id' => $this->check_agent_id,
            'status_code' => $this->status_code,
            'body_hash' => $this->body_hash,
            'response_time_ms' => $this->response_time_ms,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
