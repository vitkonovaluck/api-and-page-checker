<?php

declare(strict_types=1);

namespace App\Livewire\Sites;

use App\Livewire\Concerns\InteractsWithResponseTimeMetric;
use App\Models\Address;
use App\Models\Site;
use App\Services\CheckingGuard;
use App\Services\CheckStats;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    use InteractsWithResponseTimeMetric;

    public Site $site;

    /**
     * @var list<int>
     */
    public array $busySiteIds = [];

    public bool $checksBusy = false;

    public function mount(Site $site, CheckingGuard $guard): void
    {
        $this->site = $site;
        $this->syncBusyState($guard);
        $this->hydrateResponseTimeMetric();
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
        $this->syncBusyState($guard);
    }

    public function render(CheckStats $checkStats, CheckingGuard $guard): View
    {
        $this->syncBusyState($guard);
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

    public function checksBusy(): bool
    {
        return $this->checksBusy;
    }

    private function syncBusyState(CheckingGuard $guard): void
    {
        $this->busySiteIds = $guard->busySiteIds();
        $this->checksBusy = in_array($this->site->id, $this->busySiteIds, true);
    }
}
