<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Site
 */
final class SiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'base_url' => $this->base_url,
            'schedule_enabled' => (bool) $this->schedule_enabled,
            'schedule_interval' => $this->schedule_interval,
            'requests_per_minute' => $this->checksPerMinute(),
            'organization_id' => $this->organization_id,
            'ssl_expires_at' => $this->ssl_expires_at?->toISOString(),
            'addresses' => AddressResource::collection($this->whenLoaded('addresses')),
        ];
    }
}
