<?php

namespace App\Livewire\Addresses;

use App\Models\Address;
use App\Models\Site;
use App\Models\Snapshot;
use App\Services\CheckingGuard;
use App\Services\CheckStats;
use App\Services\DiffService;
use Livewire\Component;
use Livewire\WithPagination;

class Show extends Component
{
    use WithPagination;

    public Site $site;

    public Address $address;

    public bool $checksBusy = false;

    public function mount(Site $site, Address $address, CheckingGuard $guard): void
    {
        abort_unless($address->site_id === $site->id, 404);

        $this->site = $site;
        $this->address = $address;
        $this->checksBusy = $guard->isBusy($site->id);
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
        $this->checksBusy = $guard->isBusy($this->site->id);
    }

    public function render(DiffService $diffService, CheckStats $checkStats, CheckingGuard $guard)
    {
        $this->checksBusy = $guard->isBusy($this->site->id);
        $this->address->setRelation('site', $this->site);

        $latest = Snapshot::query()
            ->where('address_id', $this->address->id)
            ->orderByDesc('id')
            ->first();

        $previous = $latest?->previous();
        $diff = $latest ? $diffService->compare($previous, $latest) : null;
        $stats = $checkStats->forAddress($this->address);

        $snapshots = Snapshot::query()
            ->where('address_id', $this->address->id)
            ->orderByDesc('id')
            ->select([
                'id',
                'address_id',
                'status_code',
                'response_time_ms',
                'error_message',
                'created_at',
            ])
            ->paginate(20);

        return view('livewire.addresses.show', [
            'snapshots' => $snapshots,
            'latest' => $latest,
            'diff' => $diff,
            'stats' => $stats,
        ])->title(($this->address->name ?: $this->address->endpoint).' — API Snapshot Checker');
    }
}
