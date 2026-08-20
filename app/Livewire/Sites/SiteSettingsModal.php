<?php

declare(strict_types=1);

namespace App\Livewire\Sites;

use App\Models\Site;
use App\Models\User;
use App\Services\SiteTransferService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
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

    public int $requestsPerMinute = Site::CHECKS_PER_MINUTE_DEFAULT;

    /** @var list<int> */
    public array $address_schedule = [];

    public function mount(Site $site): void
    {
        $this->authorize('update', $site);
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
            'requestsPerMinute' => [
                'required',
                'integer',
                'min:'.Site::CHECKS_PER_MINUTE_MIN,
                'max:'.Site::CHECKS_PER_MINUTE_MAX,
            ],
        ]);

        $scheduleEnabled = (bool) $this->schedule_enabled;

        $this->site->fill([
            'name' => $validated['name'],
            'base_url' => rtrim($validated['base_url'], '/'),
            'schedule_enabled' => $scheduleEnabled,
            'schedule_interval' => $scheduleEnabled
                ? ($validated['schedule_interval'] ?? $this->defaultScheduleInterval())
                : $this->site->schedule_interval,
            'requests_per_minute' => (int) $validated['requestsPerMinute'],
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

    public function updatedScheduleEnabled(): void
    {
        if ($this->schedule_enabled && ($this->schedule_interval === null || $this->schedule_interval === '')) {
            $this->schedule_interval = $this->defaultScheduleInterval();
        }
    }

    public function copy(SiteTransferService $transfer): void
    {
        $this->authorize('view', $this->site);

        $user = Auth::user();
        assert($user instanceof User);

        $copy = $transfer->copy($this->site, $user);

        $this->show = false;
        session()->flash('success', 'Сайт скопійовано.');
        $this->redirect(route('sites.show', $copy), navigate: true);
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

    public function render(): View
    {
        $this->site->loadMissing(['addresses' => fn ($q) => $q->orderBy('id')]);

        return view('livewire.sites.site-settings-modal', [
            'snapshotsCount' => $this->site->snapshots()->count(),
        ]);
    }

    private function fillFromSite(): void
    {
        $this->name = $this->site->name;
        $this->base_url = $this->site->base_url;
        $this->schedule_enabled = (bool) $this->site->schedule_enabled;
        $this->schedule_interval = $this->site->schedule_interval ?: $this->defaultScheduleInterval();
        $this->requestsPerMinute = $this->resolvedRequestsPerMinute();
        $this->address_schedule = $this->site->addresses
            ->where('schedule_enabled', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function resolvedRequestsPerMinute(): int
    {
        if ($this->site->requests_per_minute !== null) {
            return max(Site::CHECKS_PER_MINUTE_MIN, (int) $this->site->requests_per_minute);
        }

        $fallback = (int) config('checking.requests_per_minute', Site::CHECKS_PER_MINUTE_DEFAULT);

        if ($fallback < Site::CHECKS_PER_MINUTE_MIN) {
            return Site::CHECKS_PER_MINUTE_DEFAULT;
        }

        return min(Site::CHECKS_PER_MINUTE_MAX, $fallback);
    }

    private function defaultScheduleInterval(): string
    {
        return (string) array_key_first(Site::SCHEDULE_INTERVALS);
    }
}
