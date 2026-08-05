<?php

namespace App\Livewire\Addresses;

use App\Models\Address;
use App\Models\Site;
use App\Models\Snapshot;
use App\Services\CheckStats;
use App\Services\DiffService;
use Livewire\Component;

class Show extends Component
{
    public Site $site;

    public Address $address;

    public function mount(Site $site, Address $address): void
    {
        abort_unless($address->site_id === $site->id, 404);

        $this->site = $site;
        $this->address = $address;
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

    public function render(DiffService $diffService, CheckStats $checkStats)
    {
        $this->address->setRelation('site', $this->site);
        $this->address->load(['snapshots' => fn ($q) => $q->orderByDesc('id')]);

        $snapshots = $this->address->snapshots;
        $latest = $snapshots->first();
        $previous = $latest?->previous();
        $diff = $latest ? $diffService->compare($previous, $latest) : null;
        $stats = $checkStats->forSnapshots($snapshots);

        return view('livewire.addresses.show', [
            'snapshots' => $snapshots,
            'latest' => $latest,
            'diff' => $diff,
            'stats' => $stats,
        ])->title(($this->address->name ?: $this->address->endpoint).' — API Snapshot Checker');
    }
}
