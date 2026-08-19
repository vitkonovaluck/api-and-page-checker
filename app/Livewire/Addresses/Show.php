<?php

declare(strict_types=1);

namespace App\Livewire\Addresses;

use App\Livewire\Concerns\InteractsWithResponseTimeMetric;
use App\Models\Address;
use App\Models\Site;
use App\Models\Snapshot;
use App\Services\CheckingGuard;
use App\Services\CheckStats;
use App\Services\DiffService;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Show extends Component
{
    use InteractsWithResponseTimeMetric;
    use WithPagination;

    public Site $site;

    public Address $address;

    /**
     * @var list<int>
     */
    public array $busySiteIds = [];

    public bool $checksBusy = false;

    public function mount(Site $site, Address $address, CheckingGuard $guard): void
    {
        abort_unless($address->site_id === $site->id, 404);

        $this->site = $site;
        $this->address = $address;
        $this->syncBusyState($guard);
        $this->hydrateResponseTimeMetric();
    }

    public function deleteSnapshot(int $snapshotId): void
    {
        $snapshot = Snapshot::query()
            ->where('address_id', $this->address->id)
            ->whereKey($snapshotId)
            ->firstOrFail();

        $snapshot->delete();
        session()->flash('success', 'Знімок видалено.');
        $this->redirect(route('addresses.show', [$this->site, $this->address]), navigate: true);
    }

    public function refreshData(CheckingGuard $guard): void
    {
        $this->site->refresh();
        $this->address->refresh();
        $this->syncBusyState($guard);
    }

    public function render(DiffService $diffService, CheckStats $checkStats, CheckingGuard $guard): View
    {
        $this->syncBusyState($guard);
        $this->address->setRelation('site', $this->site);

        $latest = Snapshot::query()
            ->where('address_id', $this->address->id)
            ->orderByDesc('id')
            ->first();

        $previous = $latest?->previous();
        $diff = $latest ? $diffService->compare($previous, $latest) : null;
        $metric = $this->responseTimeMetric();
        $stats = $checkStats->forAddress($this->address, $metric);

        $snapshots = Snapshot::query()
            ->where('address_id', $this->address->id)
            ->orderByDesc('id')
            ->select([
                'id',
                'address_id',
                'status_code',
                'response_time_ms',
                'timing',
                'error_message',
                'created_at',
            ])
            ->paginate(20);

        return view('livewire.addresses.show', [
            'snapshots' => $snapshots,
            'latest' => $latest,
            'diff' => $diff,
            'stats' => $stats,
            'metricEnum' => $metric,
        ])->title(($this->address->name ?: $this->address->endpoint).' — API Snapshot Checker');
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
