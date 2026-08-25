<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Agent;

use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Agent\AgentSocialProviderResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AgentProviderController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AgentSocialProviderResource::collection(SocialProvider::configured());
    }
}
