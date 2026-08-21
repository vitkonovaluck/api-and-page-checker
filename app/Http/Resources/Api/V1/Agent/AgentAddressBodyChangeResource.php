<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Agent;

use App\DTOs\AddressBodyChangeDTO;
use App\Models\Snapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AddressBodyChangeDTO
 */
final class AgentAddressBodyChangeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...((new AgentAddressResource($this->address))->toArray($request)),
            'latest_snapshot' => $this->snapshotPayload($this->latest),
            'previous_snapshot' => $this->snapshotPayload($this->previous),
            'body' => $this->body,
        ];
    }

    /**
     * @return array{id: int, status_code: int|null, body_hash: string|null, created_at: string|null}
     */
    private function snapshotPayload(Snapshot $snapshot): array
    {
        return [
            'id' => $snapshot->id,
            'status_code' => $snapshot->status_code,
            'body_hash' => $snapshot->body_hash,
            'created_at' => $snapshot->created_at?->toISOString(),
        ];
    }
}
