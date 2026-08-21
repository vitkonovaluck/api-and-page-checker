<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Agent\AgentSiteResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AgentSiteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        assert($user instanceof User);

        $sites = $user->sites()
            ->with(['addresses' => fn ($query) => $query->orderBy('id')])
            ->orderBy('id')
            ->get();

        return AgentSiteResource::collection($sites);
    }
}
