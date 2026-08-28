<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAddressRequest;
use App\Http\Requests\Api\V1\UpdateAddressRequest;
use App\Http\Resources\Api\V1\AddressResource;
use App\Models\Address;
use App\Models\Site;
use App\Models\User;
use App\Services\PlanQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class AddressApiController extends Controller
{
    public function index(Site $site): AnonymousResourceCollection
    {
        $this->authorize('view', $site);

        $addresses = $site->addresses()->orderBy('id')->get();

        return AddressResource::collection($addresses);
    }

    public function store(StoreAddressRequest $request, Site $site, PlanQuota $quota): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);
        $quota->assertCanCreateAddresses($user, $site, 1);

        $validated = $request->validated();
        $validated['endpoint'] = $this->normalizeEndpoint($validated['endpoint']);
        $address = $site->addresses()->create($validated);

        return (new AddressResource($address))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Site $site, Address $address): AddressResource
    {
        $this->authorize('view', $site);
        abort_unless($address->site_id === $site->id, 404);

        return new AddressResource($address);
    }

    public function update(UpdateAddressRequest $request, Site $site, Address $address): AddressResource
    {
        abort_unless($address->site_id === $site->id, 404);

        $validated = $request->validated();

        if (isset($validated['endpoint'])) {
            $validated['endpoint'] = $this->normalizeEndpoint($validated['endpoint']);
        }

        $address->fill($validated)->save();

        return new AddressResource($address);
    }

    public function destroy(Site $site, Address $address): Response
    {
        $this->authorize('update', $site);
        abort_unless($address->site_id === $site->id, 404);
        $address->delete();

        return response()->noContent();
    }

    private function normalizeEndpoint(string $endpoint): string
    {
        $endpoint = trim($endpoint);

        if ($endpoint === '') {
            return '/';
        }

        if (! str_starts_with($endpoint, '/')) {
            $endpoint = '/'.$endpoint;
        }

        return $endpoint;
    }
}
