<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Agent;

use App\Models\Address;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Site
 */
final class AgentSiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->addresses->each(function (Address $address): void {
            $address->setRelation('site', $this->resource);
        });

        return [
            'id' => $this->id,
            'name' => $this->name,
            'base_url' => $this->base_url,
            'requests_per_minute' => $this->checksPerMinute(),
            'schedule_enabled' => (bool) $this->schedule_enabled,
            'schedule_interval' => $this->schedule_interval,
            'addresses' => AgentAddressResource::collection($this->whenLoaded('addresses')),
        ];
    }
}
