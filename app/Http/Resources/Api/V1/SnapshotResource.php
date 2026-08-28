<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Snapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Snapshot
 */
final class SnapshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'address_id' => $this->address_id,
            'check_run_id' => $this->check_run_id,
            'status_code' => $this->status_code,
            'response_time_ms' => $this->response_time_ms,
            'error_message' => $this->error_message,
            'assertion_failed' => (bool) $this->assertion_failed,
            'check_outcome' => $this->check_outcome?->value,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
