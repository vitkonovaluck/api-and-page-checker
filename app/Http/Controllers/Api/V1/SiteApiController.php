<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\EnsurePersonalOrganizationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSiteRequest;
use App\Http\Requests\Api\V1\UpdateSiteRequest;
use App\Http\Resources\Api\V1\SiteResource;
use App\Models\Site;
use App\Models\User;
use App\Services\PlanQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class SiteApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $this->user($request);

        $sites = $user->accessibleSites()
            ->with(['addresses' => fn ($query) => $query->orderBy('id')])
            ->orderBy('id')
            ->get();

        return SiteResource::collection($sites);
    }

    public function store(
        StoreSiteRequest $request,
        PlanQuota $quota,
        EnsurePersonalOrganizationAction $ensureOrganization,
    ): JsonResponse {
        $user = $this->user($request);
        $quota->assertCanCreateSite($user);
        $validated = $request->validated();
        $organization = $ensureOrganization->execute($user);

        $site = $user->sites()->create([
            'name' => $validated['name'],
            'base_url' => rtrim($validated['base_url'], '/'),
            'organization_id' => $organization->id,
        ]);

        return (new SiteResource($site))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Site $site): SiteResource
    {
        $this->authorize('view', $site);
        $site->load(['addresses' => fn ($query) => $query->orderBy('id')]);

        return new SiteResource($site);
    }

    public function update(UpdateSiteRequest $request, Site $site): SiteResource
    {
        $validated = $request->validated();

        if (isset($validated['base_url'])) {
            $validated['base_url'] = rtrim($validated['base_url'], '/');
        }

        $site->fill($validated)->save();

        return new SiteResource($site);
    }

    public function destroy(Site $site): Response
    {
        $this->authorize('delete', $site);
        $site->delete();

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
