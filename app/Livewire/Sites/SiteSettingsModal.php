<?php

namespace App\Livewire\Sites;

use App\Models\Site;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class SiteSettingsModal extends Component
{
    public Site $site;

    public bool $show = false;

    public string $name = '';

    public string $base_url = '';

    public bool $schedule_enabled = false;

    public ?string $schedule_interval = null;

    public int $requests_per_minute = 30;

    /** @var list<int> */
    public array $address_schedule = [];

    public function mount(Site $site): void
    {
        $this->site = $site;
        $this->fillFromSite();
    }

    #[On('open-site-settings')]
    public function open(): void
    {
        $this->site->refresh();
        $this->site->load(['addresses' => fn ($q) => $q->orderBy('id')]);
        $this->fillFromSite();
        $this->resetValidation();
        $this->show = true;
    }

    public function close(): void
    {
        $this->show = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'url', 'max:2048'],
            'schedule_enabled' => ['boolean'],
            'schedule_interval' => [
                'nullable',
                Rule::in(array_keys(Site::SCHEDULE_INTERVALS)),
                Rule::requiredIf(fn () => $this->schedule_enabled),
            ],
            'address_schedule' => ['nullable', 'array'],
            'address_schedule.*' => ['integer'],
            'requests_per_minute' => ['required', 'integer', 'min:1', 'max:120'],
        ]);

        $this->site->fill([
            'name' => $validated['name'],
            'base_url' => rtrim($validated['base_url'], '/'),
            'schedule_enabled' => $this->schedule_enabled,
            'schedule_interval' => $this->schedule_enabled
                ? ($validated['schedule_interval'] ?? null)
                : $this->site->schedule_interval,
            'requests_per_minute' => (int) $validated['requests_per_minute'],
        ])->save();

        $enabledIds = collect($validated['address_schedule'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->site->load('addresses');

        foreach ($this->site->addresses as $address) {
            $address->forceFill([
                'schedule_enabled' => in_array($address->id, $enabledIds, true),
            ])->save();
        }

        $this->show = false;
        session()->flash('success', 'Сайт оновлено.');
        $this->redirect(route('sites.show', $this->site), navigate: true);
    }

    public function clearSnapshots(): void
    {
        $deleted = $this->site->snapshots()->delete();

        $this->show = false;
        session()->flash(
            'success',
            $deleted > 0
                ? "Видалено знімків: {$deleted}."
                : 'Знімків для очищення немає.',
        );
        $this->redirect(route('sites.show', $this->site), navigate: true);
    }

    public function render()
    {
        $this->site->loadMissing(['addresses' => fn ($q) => $q->orderBy('id')]);

        $preview = $this->previewSite();

        return view('livewire.sites.site-settings-modal', [
            'snapshotsCount' => $this->site->snapshots()->count(),
            'checkIntervalSeconds' => round($preview->checkIntervalMilliseconds() / 1000, 1),
            'estimatedDurationLabel' => $this->formatDuration(
                $preview->estimatedCheckDurationSeconds($this->site->addresses->count())
            ),
            'scheduleMayOverlap' => $this->scheduleMayOverlap(),
        ]);
    }

    private function fillFromSite(): void
    {
        $this->name = $this->site->name;
        $this->base_url = $this->site->base_url;
        $this->schedule_enabled = (bool) $this->site->schedule_enabled;
        $this->schedule_interval = $this->site->schedule_interval;
        $this->requests_per_minute = $this->site->requestsPerMinute();
        $this->address_schedule = $this->site->addresses
            ->where('schedule_enabled', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function previewSite(): Site
    {
        return new Site([
            'requests_per_minute' => max(1, $this->requests_per_minute),
        ]);
    }

    private function scheduleMayOverlap(): bool
    {
        if (! $this->schedule_enabled) {
            return false;
        }

        $minutes = Site::SCHEDULE_INTERVALS[$this->schedule_interval ?? ''] ?? null;
        if ($minutes === null) {
            return false;
        }

        $duration = $this->previewSite()->estimatedCheckDurationSeconds($this->site->addresses->count());

        return $duration > ($minutes * 60);
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 1) {
            return '—';
        }

        if ($seconds < 60) {
            return "≈ {$seconds} с";
        }

        $minutes = (int) ceil($seconds / 60);
        if ($minutes < 60) {
            return "≈ {$minutes} хв";
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        if ($rest === 0) {
            return "≈ {$hours} год";
        }

        return "≈ {$hours} год {$rest} хв";
    }
}
