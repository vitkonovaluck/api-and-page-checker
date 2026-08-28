<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Address
 */
final class AddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'name' => $this->name,
            'endpoint' => $this->endpoint,
            'http_method' => $this->http_method ?? 'GET',
            'kind' => $this->kind?->value ?? 'http',
            'schedule_enabled' => (bool) $this->schedule_enabled,
            'ignore_json_paths' => $this->ignore_json_paths ?? [],
            'ignore_headers' => $this->ignore_headers ?? [],
            'watch_json_paths' => $this->watch_json_paths ?? [],
            'assertions' => $this->assertions ?? [],
            'step_order' => $this->step_order,
            'extract_json_path' => $this->extract_json_path,
            'extract_as' => $this->extract_as,
            'baseline_snapshot_id' => $this->baseline_snapshot_id,
            'last_checked_at' => $this->last_checked_at?->toISOString(),
        ];
    }
}
