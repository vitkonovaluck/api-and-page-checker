<?php

declare(strict_types=1);

namespace App\Livewire\Sites;

use App\Actions\StopManualCheckRunAction;
use App\Models\Site;
use App\Models\User;
use App\Services\CheckingGuard;
use App\Services\CheckStats;
use App\Services\PlanQuota;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Список сайтів — API Snapshot Checker')]
class Index extends Component
{
    /**
     * @var list<int>
     */
    public array $busySiteIds = [];

    public bool $checksBusy = false;

    public function mount(CheckingGuard $guard): void
    {
        $this->syncBusyState($guard);
    }

    public function delete(Site $site): void
    {
        $this->authorize('delete', $site);

        $site->delete();

        session()->flash('success', 'Сайт видалено.');
        $this->redirect(route('sites.index'), navigate: true);
    }

    public function stopManualCheckRun(int $siteId, StopManualCheckRunAction $action): void
    {
        $site = $this->currentUser()->sites()->whereKey($siteId)->firstOrFail();
        $this->authorize('update', $site);

        if (! $action->queue($site)) {
            session()->flash('error', 'Немає активної ручної перевірки для зупинки.');
            $this->redirect(route('sites.index'), navigate: true);

            return;
        }

        session()->flash('success', 'Перевірку зупинено. Дані цього проходу видаляються.');
        $this->redirect(route('sites.index'), navigate: true);
    }

    public function refreshData(CheckingGuard $guard): void
    {
        $this->syncBusyState($guard);
    }

    public function render(
        CheckingGuard $guard,
        PlanQuota $quota,
        StopManualCheckRunAction $stopManualCheck,
        CheckStats $checkStats,
    ): View {
        $user = $this->currentUser();
        $sites = $user->sites()
            ->withCount('addresses')
            ->with(['addresses' => fn ($q) => $q->orderByDesc('last_checked_at')])
            ->orderByDesc('updated_at')
            ->get();

        $this->syncBusyState($guard, $sites->modelKeys());
        $usage = $quota->siteUsage($user);

        return view('livewire.sites.index', [
            'sites' => $sites,
            'canCreateSite' => $usage['can_create_site'],
            'quotaSummary' => $this->quotaSummary($usage),
            'stoppableSiteIds' => $stopManualCheck->stoppableSiteIds($sites->modelKeys()),
            'checkTimes' => $this->formatCheckTimes($checkStats->averageResponseTimesForSites($sites)),
        ]);
    }

    /**
     * @param  array{
     *     sites_used: int,
     *     sites_max: int|null,
     *     addresses_used: int,
     *     addresses_total_max: int|null,
     *     addresses_per_site_max: int|null
     * }  $usage
     */
    private function quotaSummary(array $usage): string
    {
        $sitesMax = $usage['sites_max'] === null || $usage['sites_max'] === ''
            ? '∞'
            : (string) $usage['sites_max'];
        $parts = ['сайти '.$usage['sites_used'].'/'.$sitesMax];

        if ($usage['addresses_total_max'] !== null && $usage['addresses_total_max'] !== '') {
            $parts[] = 'адреси '.$usage['addresses_used'].'/'.$usage['addresses_total_max'];
        }

        if ($usage['addresses_per_site_max'] !== null && $usage['addresses_per_site_max'] !== '') {
            $parts[] = 'до '.$usage['addresses_per_site_max'].' адрес на сайт';
        }

        return implode(', ', $parts);
    }

    public function checksBusy(): bool
    {
        return $this->checksBusy;
    }

    /**
     * @param  array<int, array{
     *     avg_latest_response_time_ms: int|null,
     *     avg_hour_response_time_ms: int|null,
     *     avg_day_response_time_ms: int|null,
     *     avg_all_response_time_ms: int|null
     * }>  $checkTimes
     * @return array<int, string>
     */
    private function formatCheckTimes(array $checkTimes): array
    {
        $formatted = [];
        foreach ($checkTimes as $siteId => $times) {
            $formatted[$siteId] = implode('/ ', [
                $this->formatAverageMs($times['avg_latest_response_time_ms']),
                $this->formatAverageMs($times['avg_hour_response_time_ms']),
                $this->formatAverageMs($times['avg_day_response_time_ms']),
                $this->formatAverageMs($times['avg_all_response_time_ms']),
            ]);
        }

        return $formatted;
    }

    private function formatAverageMs(?int $milliseconds): string
    {
        return $milliseconds !== null ? $milliseconds.' ms' : '—';
    }

    /**
     * @param  list<int|string>  $siteIds
     */
    private function syncBusyState(CheckingGuard $guard, array $siteIds = []): void
    {
        $this->busySiteIds = $guard->busySiteIdsAmong(array_map(intval(...), $siteIds));
        $this->checksBusy = $this->busySiteIds !== [];
    }

    private function currentUser(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
