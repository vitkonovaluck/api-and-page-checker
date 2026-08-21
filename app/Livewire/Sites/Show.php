<?php

declare(strict_types=1);

namespace App\Livewire\Sites;

use App\Actions\DeleteLatestManualCheckRunAction;
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
        $this->authorize('view', $site);
        $this->site = $site;
        $this->syncBusyState($guard);
        $this->hydrateResponseTimeMetric();
    }

    public function deleteAddress(int $addressId): void
    {
        $this->authorize('update', $this->site);

        $address = Address::query()
            ->where('site_id', $this->site->id)
            ->whereKey($addressId)
            ->firstOrFail();

        $address->delete();
        session()->flash('success', 'Адресу видалено.');
        $this->redirect(route('sites.show', $this->site), navigate: true);
    }

    public function deleteLastManualCheckRun(DeleteLatestManualCheckRunAction $action, CheckingGuard $guard): void
    {
        $this->authorize('update', $this->site);
        $this->syncBusyState($guard);

        if ($this->checksBusy) {
            $this->redirectWithFlash('error', 'Зараз уже виконується перевірка. Зачекайте завершення.');

            return;
        }

        if (! $action->queue($this->site)) {
            $this->redirectWithFlash('error', 'Немає завершеного ручного проходу для видалення.');

            return;
        }

        $this->redirectWithFlash('success', 'Видалення останнього проходу запущено.');
    }

    public function refreshData(CheckingGuard $guard): void
    {
        $this->site->refresh();
        $this->syncBusyState($guard);
    }

    public function render(
        CheckStats $checkStats,
        CheckingGuard $guard,
        DeleteLatestManualCheckRunAction $deleteLatestManualCheckRun,
    ): View {
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
            'canDeleteLastManualRun' => $deleteLatestManualCheckRun->find($this->site) !== null
                && ! $deleteLatestManualCheckRun->isDeleting($this->site),
            'isDeletingLastManualRun' => $deleteLatestManualCheckRun->isDeleting($this->site),
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

    private function redirectWithFlash(string $type, string $message): void
    {
        session()->flash($type, $message);
        $this->redirect(route('sites.show', $this->site), navigate: true);
    }
}
