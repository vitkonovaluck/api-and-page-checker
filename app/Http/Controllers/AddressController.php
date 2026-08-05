<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Site;
use App\Models\Snapshot;
use App\Services\CheckStats;
use App\Services\DiffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AddressController extends Controller
{
    public function store(Request $request, Site $site): RedirectResponse
    {
        $request->merge([
            'endpoint' => $this->normalizeEndpoint((string) $request->input('endpoint', '')),
        ]);

        $validated = $request->validate([
            'endpoint' => [
                'required',
                'string',
                'max:766',
                Rule::unique('addresses', 'endpoint')->where(fn ($q) => $q->where('site_id', $site->id)),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'schedule_enabled' => ['sometimes', 'boolean'],
            'headers' => ['nullable', 'array'],
            'headers.*.name' => ['nullable', 'string', 'max:255'],
            'headers.*.value' => ['nullable', 'string', 'max:2048'],
        ], [
            'endpoint.unique' => 'Цей ендпоїнт уже додано до сайту.',
        ]);

        $site->addresses()->create([
            'name' => $validated['name'] ?? null,
            'endpoint' => $validated['endpoint'],
            'schedule_enabled' => $request->boolean('schedule_enabled'),
            'request_headers' => $this->normalizeRequestHeaders($validated['headers'] ?? []),
        ]);

        return redirect()
            ->route('sites.show', $site)
            ->with('success', 'Адресу додано.');
    }

    public function update(Request $request, Site $site, Address $address): RedirectResponse
    {
        abort_unless($address->site_id === $site->id, 404);

        $validated = $request->validate([
            'headers' => ['nullable', 'array'],
            'headers.*.name' => ['nullable', 'string', 'max:255'],
            'headers.*.value' => ['nullable', 'string', 'max:2048'],
        ]);

        $address->update([
            'request_headers' => $this->normalizeRequestHeaders($validated['headers'] ?? []),
        ]);

        return redirect()
            ->route('addresses.show', [$site, $address])
            ->with('success', 'Налаштування адреси збережено.');
    }

    public function show(
        Request $request,
        Site $site,
        Address $address,
        DiffService $diffService,
        CheckStats $checkStats,
    ): View {
        abort_unless($address->site_id === $site->id, 404);

        $address->setRelation('site', $site);
        $address->load(['snapshots' => fn ($q) => $q->orderByDesc('id')]);

        $latest = $address->snapshots->first();
        $previous = $latest?->previous();
        $diff = $latest ? $diffService->compare($previous, $latest) : null;
        $stats = $checkStats->forSnapshots($address->snapshots);
        $responseTimeChart = $checkStats->responseTimeChartForAddress(
            $address,
            $request->query('period'),
        );

        return view('addresses.show', [
            'site' => $site,
            'address' => $address,
            'snapshots' => $address->snapshots,
            'latest' => $latest,
            'diff' => $diff,
            'stats' => $stats,
            'responseTimeChart' => $responseTimeChart,
        ]);
    }

    public function destroy(Site $site, Address $address): RedirectResponse
    {
        abort_unless($address->site_id === $site->id, 404);

        $address->delete();

        return redirect()
            ->route('sites.show', $site)
            ->with('success', 'Адресу видалено.');
    }

    public function showSnapshot(Site $site, Address $address, Snapshot $snapshot, DiffService $diffService): View
    {
        abort_unless($address->site_id === $site->id, 404);
        abort_unless($snapshot->address_id === $address->id, 404);

        $address->setRelation('site', $site);
        $previous = $snapshot->previous();
        $diff = $diffService->compare($previous, $snapshot);

        return view('addresses.snapshot', [
            'site' => $site,
            'address' => $address,
            'snapshot' => $snapshot,
            'previous' => $previous,
            'diff' => $diff,
        ]);
    }

    public function destroySnapshot(Site $site, Address $address, Snapshot $snapshot): RedirectResponse
    {
        abort_unless($address->site_id === $site->id, 404);
        abort_unless($snapshot->address_id === $address->id, 404);

        $snapshot->delete();

        return redirect()
            ->route('addresses.show', [$site, $address])
            ->with('success', 'Знімок видалено.');
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

    /**
     * @param  array<int, array{name?: string|null, value?: string|null}>  $rows
     * @return array<string, string>|null
     */
    private function normalizeRequestHeaders(array $rows): ?array
    {
        $headers = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $headers[$name] = (string) ($row['value'] ?? '');
        }

        return $headers === [] ? null : $headers;
    }
}
