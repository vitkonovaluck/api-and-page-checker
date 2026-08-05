<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Services\CheckStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function index(): View
    {
        $sites = Site::query()
            ->withCount('addresses')
            ->with(['addresses' => fn ($q) => $q->orderByDesc('last_checked_at')])
            ->orderByDesc('updated_at')
            ->get();

        return view('sites.index', compact('sites'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'url', 'max:2048'],
            'endpoint' => ['nullable', 'string', 'max:766'],
            'address_name' => ['nullable', 'string', 'max:255'],
        ]);

        $site = Site::query()->create([
            'name' => $validated['name'],
            'base_url' => rtrim($validated['base_url'], '/'),
        ]);

        if (! empty($validated['endpoint'])) {
            $site->addresses()->create([
                'name' => $validated['address_name'] ?? null,
                'endpoint' => $this->normalizeEndpoint($validated['endpoint']),
            ]);
        }

        return redirect()
            ->route('sites.show', $site)
            ->with('success', 'Сайт створено.');
    }

    public function show(Request $request, Site $site, CheckStats $checkStats): View
    {
        $site->load(['addresses' => fn ($q) => $q->with('latestSnapshot')->orderBy('id')]);

        $addressStats = $checkStats->forAddresses($site->addresses);
        $scheduleStats = $site->schedule_enabled
            ? $checkStats->forSite($site, scheduledOnly: true)
            : null;
        $siteStats = $checkStats->forSite($site, scheduledOnly: false);
        $responseTimeChart = $checkStats->responseTimeChartForSite(
            $site,
            $request->query('period'),
        );

        return view('sites.show', compact(
            'site',
            'addressStats',
            'scheduleStats',
            'siteStats',
            'responseTimeChart',
        ));
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'url', 'max:2048'],
            'schedule_enabled' => ['sometimes', 'boolean'],
            'schedule_interval' => [
                'nullable',
                Rule::in(array_keys(Site::SCHEDULE_INTERVALS)),
                Rule::requiredIf(fn () => $request->boolean('schedule_enabled')),
            ],
            'address_schedule' => ['nullable', 'array'],
            'address_schedule.*' => ['integer'],
        ]);

        $site->fill([
            'name' => $validated['name'],
            'base_url' => rtrim($validated['base_url'], '/'),
            'schedule_enabled' => $request->boolean('schedule_enabled'),
            'schedule_interval' => $request->boolean('schedule_enabled')
                ? ($validated['schedule_interval'] ?? null)
                : $site->schedule_interval,
        ])->save();

        if ($request->has('address_schedule_submitted')) {
            $enabledIds = collect($validated['address_schedule'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->all();

            $site->load('addresses');

            foreach ($site->addresses as $address) {
                $address->forceFill([
                    'schedule_enabled' => in_array($address->id, $enabledIds, true),
                ])->save();
            }
        }

        return redirect()
            ->route('sites.show', $site)
            ->with('success', 'Сайт оновлено.');
    }

    public function copy(Site $site): RedirectResponse
    {
        $site->load('addresses');

        $copy = DB::transaction(function () use ($site) {
            $newSite = Site::query()->create([
                'name' => $site->name.' (копія)',
                'base_url' => $site->base_url,
                'schedule_enabled' => $site->schedule_enabled,
                'schedule_interval' => $site->schedule_interval,
                'schedule_last_run_at' => null,
            ]);

            foreach ($site->addresses as $address) {
                $newSite->addresses()->create([
                    'name' => $address->name,
                    'endpoint' => $address->endpoint,
                    'schedule_enabled' => $address->schedule_enabled,
                    'request_headers' => $address->request_headers,
                    'last_checked_at' => null,
                ]);
            }

            return $newSite;
        });

        return redirect()
            ->route('sites.show', $copy)
            ->with('success', 'Сайт скопійовано.');
    }

    public function destroy(Site $site): RedirectResponse
    {
        $site->delete();

        return redirect()
            ->route('sites.index')
            ->with('success', 'Сайт видалено.');
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
