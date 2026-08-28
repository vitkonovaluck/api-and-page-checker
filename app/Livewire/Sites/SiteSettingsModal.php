<?php

declare(strict_types=1);

namespace App\Livewire\Sites;

use App\Models\Site;
use App\Models\SiteToken;
use App\Models\User;
use App\Services\SiteTransferService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    /** @var list<array{id: int|null, name: string, value: string}> */
    public array $tokens = [['id' => null, 'name' => '', 'value' => '']];

    public function mount(Site $site): void
    {
        $this->authorize('view', $site);
        $this->site = $site;
        $this->fillFromSite();
    }

    #[On('open-site-settings')]
    public function open(): void
    {
        $this->authorize('update', $this->site);
        $this->site->refresh();
        $this->site->load([
            'addresses' => fn ($q) => $q->orderBy('id'),
            'tokens' => fn ($q) => $q->orderBy('id'),
        ]);
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
        $this->authorize('update', $this->site);
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
            'tokens' => ['nullable', 'array'],
            'tokens.*.id' => ['nullable', 'integer'],
            'tokens.*.name' => ['nullable', 'string', 'max:255'],
            'tokens.*.value' => ['nullable', 'string', 'max:8192'],
        ]);

        $normalizedTokens = $this->normalizedTokenRows($validated['tokens'] ?? []);

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

        $this->persistTokens($normalizedTokens);

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

    public function addTokenRow(): void
    {
        $this->tokens[] = $this->emptyTokenRow();
    }

    public function removeTokenRow(int $index): void
    {
        unset($this->tokens[$index]);
        $this->tokens = array_values($this->tokens);

        if ($this->tokens === []) {
            $this->tokens = [$this->emptyTokenRow()];
        }
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
        $this->authorize('update', $this->site);
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
        $this->tokens = $this->tokensToRows();
    }

    /**
     * @return list<array{id: int|null, name: string, value: string}>
     */
    private function tokensToRows(): array
    {
        $this->site->loadMissing(['tokens' => fn ($q) => $q->orderBy('id')]);

        $rows = $this->site->tokens
            ->map(fn (SiteToken $token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'value' => $token->value,
            ])
            ->values()
            ->all();

        return $rows === [] ? [$this->emptyTokenRow()] : $rows;
    }

    /**
     * @return array{id: null, name: string, value: string}
     */
    private function emptyTokenRow(): array
    {
        return ['id' => null, 'name' => '', 'value' => ''];
    }

    /**
     * @param  list<array{id?: int|null, name?: string|null, value?: string|null}>  $rows
     * @return list<array{id: int|null, name: string, value: string}>
     */
    private function normalizedTokenRows(array $rows): array
    {
        $normalized = [];

        foreach ($rows as $index => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $value = (string) ($row['value'] ?? '');
            $id = $row['id'] ?? null;
            $id = $id === null || $id === '' ? null : (int) $id;

            if ($name === '' && $value === '') {
                continue;
            }

            if ($name === '' || $value === '') {
                throw ValidationException::withMessages([
                    "tokens.{$index}.name" => 'Вкажіть назву і значення токена.',
                ]);
            }

            $normalized[] = [
                'id' => $id,
                'name' => $name,
                'value' => $value,
            ];
        }

        $names = array_column($normalized, 'name');

        if (count($names) !== count(array_unique($names))) {
            throw ValidationException::withMessages([
                'tokens' => 'Назви токенів мають бути унікальними.',
            ]);
        }

        return $normalized;
    }

    /**
     * @param  list<array{id: int|null, name: string, value: string}>  $rows
     */
    private function persistTokens(array $rows): void
    {
        $existingIds = $this->site->tokens()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $keptIds = [];

        foreach ($rows as $row) {
            if ($row['id'] === null) {
                continue;
            }

            if (! in_array($row['id'], $existingIds, true)) {
                throw ValidationException::withMessages([
                    'tokens' => 'Токен не належить цьому сайту.',
                ]);
            }

            $keptIds[] = $row['id'];
        }

        $deleteQuery = $this->site->tokens();

        if ($keptIds !== []) {
            $deleteQuery->whereNotIn('id', $keptIds);
        }

        $deleteQuery->delete();

        foreach ($rows as $row) {
            $this->upsertToken($row);
        }
    }

    /**
     * @param  array{id: int|null, name: string, value: string}  $row
     */
    private function upsertToken(array $row): SiteToken
    {
        if ($row['id'] === null) {
            return $this->site->tokens()->create([
                'name' => $row['name'],
                'value' => $row['value'],
            ]);
        }

        $token = $this->site->tokens()->whereKey($row['id'])->first();

        if ($token === null) {
            throw ValidationException::withMessages([
                'tokens' => 'Токен не належить цьому сайту.',
            ]);
        }

        $token->fill([
            'name' => $row['name'],
            'value' => $row['value'],
        ])->save();

        return $token;
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
