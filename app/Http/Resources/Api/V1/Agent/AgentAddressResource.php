<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Agent;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Address
 */
final class AgentAddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('siteToken');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'endpoint' => $this->endpoint,
            'full_url' => $this->fullUrl(),
            'http_method' => $this->http_method ?? 'GET',
            'request_headers' => $this->resolvedRequestHeaders(),
            'request_body' => $this->request_body,
            'schedule_enabled' => (bool) $this->schedule_enabled,
        ];
    }
}
