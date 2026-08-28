<?php

declare(strict_types=1);

namespace App\Livewire\Addresses;

use App\Actions\AcceptBaselineAction;
use App\DTOs\DiffOptionsDTO;
use App\Livewire\Concerns\InteractsWithResponseTimeMetric;
use App\Models\Address;
use App\Models\Site;
use App\Models\Snapshot;
use App\Models\User;
use App\Services\CheckingGuard;
use App\Services\CheckStats;
use App\Services\DiffService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public ?int $compareFromId = null;

    public function mount(Site $site, Address $address, CheckingGuard $guard, Request $request): void
    {
        abort_unless($address->site_id === $site->id, 404);
        $this->authorize('view', $site);

        $this->site = $site;
        $this->address = $address;
        $compare = $request->integer('compare');
        $this->compareFromId = $compare > 0 ? $compare : null;
        $this->syncBusyState($guard);
        $this->hydrateResponseTimeMetric();
    }

    public function acceptBaseline(AcceptBaselineAction $action): void
    {
        $this->authorize('update', $this->site);

        $snapshot = Snapshot::query()
            ->where('address_id', $this->address->id)
            ->orderByDesc('id')
            ->firstOrFail();

        $user = Auth::user();
        assert($user instanceof User);

        $action->execute($this->address, $snapshot, $user);
        $this->address->refresh();
        session()->flash('success', __('alerts.accepted_baseline'));
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

        $options = DiffOptionsDTO::fromAddress($this->address);
        $compareFrom = $this->resolveCompareFrom($latest);
        $diff = $latest ? $diffService->compare($compareFrom, $latest, $options) : null;
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
                'check_outcome',
                'assertion_failed',
                'created_at',
            ])
            ->paginate(20);

        $compareSnapshots = Snapshot::query()
            ->where('address_id', $this->address->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'created_at', 'status_code']);

        return view('livewire.addresses.show', [
            'snapshots' => $snapshots,
            'compareSnapshots' => $compareSnapshots,
            'latest' => $latest,
            'diff' => $diff,
            'stats' => $stats,
            'metricEnum' => $metric,
            'hasOpenIncident' => $this->address->incidents()->where('status', 'open')->exists(),
        ])->title(($this->address->name ?: $this->address->endpoint).' — API Snapshot Checker');
    }

    public function checksBusy(): bool
    {
        return $this->checksBusy;
    }

    private function resolveCompareFrom(?Snapshot $latest): ?Snapshot
    {
        if ($this->compareFromId !== null && $this->compareFromId > 0) {
            return Snapshot::query()
                ->where('address_id', $this->address->id)
                ->whereKey($this->compareFromId)
                ->first();
        }

        return $latest?->previous();
    }

    private function syncBusyState(CheckingGuard $guard): void
    {
        $this->busySiteIds = $guard->busySiteIds();
        $this->checksBusy = in_array($this->site->id, $this->busySiteIds, true);
    }
}
