<?php

namespace App\Livewire\Sites;

use App\Livewire\Concerns\InteractsWithResponseTimeMetric;
use App\Models\Address;
use App\Models\Site;
use App\Services\CheckingGuard;
use App\Services\CheckStats;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Show extends Component
{
    use InteractsWithResponseTimeMetric;

    public Site $site;

    public bool $checksBusy = false;

    public function mount(Site $site, CheckingGuard $guard): void
    {
        $this->site = $site;
        $this->checksBusy = $guard->isBusy();
        $this->hydrateResponseTimeMetric();
    }

    public function copy(): void
    {
        $this->site->load('addresses');

        $copy = DB::transaction(function () {
            $newSite = Site::query()->create([
                'name' => $this->site->name.' (копія)',
                'base_url' => $this->site->base_url,
                'schedule_enabled' => $this->site->schedule_enabled,
                'schedule_interval' => $this->site->schedule_interval,
                'schedule_last_run_at' => null,
                'requests_per_minute' => $this->site->requests_per_minute,
            ]);

            foreach ($this->site->addresses as $address) {
                $newSite->addresses()->create([
                    'name' => $address->name,
                    'endpoint' => $address->endpoint,
                    'http_method' => $address->http_method,
                    'schedule_enabled' => $address->schedule_enabled,
                    'request_headers' => $address->request_headers,
                    'request_body' => $address->request_body,
                    'last_checked_at' => null,
                ]);
            }

            return $newSite;
        });

        session()->flash('success', 'Сайт скопійовано.');
        $this->redirect(route('sites.show', $copy), navigate: true);
    }

    public function deleteAddress(int $addressId): void
    {
        $address = Address::query()
            ->where('site_id', $this->site->id)
            ->whereKey($addressId)
            ->firstOrFail();

        $address->delete();
        session()->flash('success', 'Адресу видалено.');
        $this->redirect(route('sites.show', $this->site), navigate: true);
    }

    public function refreshData(CheckingGuard $guard): void
    {
        $this->site->refresh();
        $this->checksBusy = $guard->isBusy();
    }

    public function render(CheckStats $checkStats, CheckingGuard $guard)
    {
        $this->checksBusy = $guard->isBusy();
        $this->site->load(['addresses' => fn ($q) => $q->with(['latestSnapshot', 'previousSnapshot'])->orderBy('id')]);

        $metric = $this->responseTimeMetric();
        $addressStats = $checkStats->forAddresses($this->site->addresses, $metric);
        $scheduleStats = $this->site->schedule_enabled
            ? $checkStats->forSite($this->site, scheduledOnly: true, metric: $metric)
            : null;
        $siteStats = $checkStats->forSite($this->site, scheduledOnly: false, metric: $metric);

        return view('livewire.sites.show', [
            'addressStats' => $addressStats,
            'scheduleStats' => $scheduleStats,
            'siteStats' => $siteStats,
            'metricEnum' => $metric,
        ])->title($this->site->name.' — API Snapshot Checker');
    }
}
