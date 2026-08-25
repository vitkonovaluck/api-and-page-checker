<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Agent;

use App\Enums\SocialProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AgentSocialProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $provider = $this->resource;
        assert($provider instanceof SocialProvider);

        return [
            'id' => $provider->value,
            'label' => $provider->label(),
        ];
    }
}
