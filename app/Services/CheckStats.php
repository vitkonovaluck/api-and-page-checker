<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Site;
use App\Models\Snapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CheckStats
{
    /**
     * Rolling windows for response-time history charts.
     * `hours: null` means the latest snapshot value (not a window average).
     *
     * @var array<string, array{label: string, hours: int|null}>
     */
    public const RESPONSE_TIME_PERIODS = [
        'latest' => ['label' => 'Останній', 'hours' => null],
        '6h' => ['label' => '6 год', 'hours' => 6],
        '12h' => ['label' => '12 год', 'hours' => 12],
        '24h' => ['label' => '24 год', 'hours' => 24],
        '48h' => ['label' => '48 год', 'hours' => 48],
        '96h' => ['label' => '96 год', 'hours' => 96],
        '1w' => ['label' => '1 тиждень', 'hours' => 168],
    ];

    /**
     * @return array{
     *     checks_count: int,
     *     avg_response_time_ms: int|null,
     *     error_count: int,
     *     avg_errors: float|null
     * }
     */
    public function forSnapshots(Collection $snapshots): array
    {
        $count = $snapshots->count();

        if ($count === 0) {
            return $this->empty();
        }

        $errorCount = $snapshots->filter(
            fn (Snapshot $snapshot) => filled($snapshot->error_message)
        )->count();

        return [
            'checks_count' => $count,
            'avg_response_time_ms' => (int) round((float) $snapshots->avg('response_time_ms')),
            'error_count' => $errorCount,
            'avg_errors' => round($errorCount / $count, 2),
        ];
    }

    /**
     * @return array{
     *     checks_count: int,
     *     avg_response_time_ms: int|null,
     *     error_count: int,
     *     avg_errors: float|null
     * }
     */
    public function forAddress(Address $address): array
    {
        $row = Snapshot::query()
            ->where('address_id', $address->id)
            ->selectRaw('COUNT(*) as checks_count')
            ->selectRaw('AVG(response_time_ms) as avg_response_time_ms')
            ->selectRaw('SUM(CASE WHEN error_message IS NOT NULL AND error_message != \'\' THEN 1 ELSE 0 END) as error_count')
            ->first();

        return $this->fromAggregateRow($row);
    }

    /**
     * Aggregate stats for a site. When $scheduledOnly is true, only addresses
     * included in the schedule are considered.
     *
     * @return array{
     *     checks_count: int,
     *     avg_response_time_ms: int|null,
     *     error_count: int,
     *     avg_errors: float|null,
     *     runs_count: int,
     *     avg_errors_per_run: float|null,
     *     avg_response_time_per_run_ms: int|null
     * }
     */
    public function forSite(Site $site, bool $scheduledOnly = false): array
    {
        $addressQuery = $site->addresses();
        if ($scheduledOnly) {
            $addressQuery->where('schedule_enabled', true);
        }

        $addressIds = $addressQuery->pluck('id');

        if ($addressIds->isEmpty()) {
            return array_merge($this->empty(), [
                'runs_count' => 0,
                'avg_errors_per_run' => null,
                'avg_response_time_per_run_ms' => null,
            ]);
        }

        $row = Snapshot::query()
            ->whereIn('address_id', $addressIds)
            ->selectRaw('COUNT(*) as checks_count')
            ->selectRaw('AVG(response_time_ms) as avg_response_time_ms')
            ->selectRaw('SUM(CASE WHEN error_message IS NOT NULL AND error_message != \'\' THEN 1 ELSE 0 END) as error_count')
            ->first();

        $base = $this->fromAggregateRow($row);

        $runBuckets = Snapshot::query()
            ->whereIn('address_id', $addressIds)
            ->selectRaw($this->runBucketExpression().' as run_bucket')
            ->selectRaw('AVG(response_time_ms) as avg_response_time_ms')
            ->selectRaw('SUM(CASE WHEN error_message IS NOT NULL AND error_message != \'\' THEN 1 ELSE 0 END) as error_count')
            ->groupBy('run_bucket')
            ->get();

        $runsCount = $runBuckets->count();

        return array_merge($base, [
            'runs_count' => $runsCount,
            'avg_errors_per_run' => $runsCount > 0
                ? round((float) $runBuckets->avg('error_count'), 2)
                : null,
            'avg_response_time_per_run_ms' => $runsCount > 0
                ? (int) round((float) $runBuckets->avg('avg_response_time_ms'))
                : null,
        ]);
    }

    /**
     * @param  Collection<int, Address>  $addresses
     * @return array<int, array{
     *     checks_count: int,
     *     avg_response_time_ms: int|null,
     *     error_count: int,
     *     avg_errors: float|null
     * }>
     */
    public function forAddresses(Collection $addresses): array
    {
        if ($addresses->isEmpty()) {
            return [];
        }

        $rows = Snapshot::query()
            ->whereIn('address_id', $addresses->pluck('id'))
            ->selectRaw('address_id')
            ->selectRaw('COUNT(*) as checks_count')
            ->selectRaw('AVG(response_time_ms) as avg_response_time_ms')
            ->selectRaw('SUM(CASE WHEN error_message IS NOT NULL AND error_message != \'\' THEN 1 ELSE 0 END) as error_count')
            ->groupBy('address_id')
            ->get()
            ->keyBy('address_id');

        $stats = [];
        foreach ($addresses as $address) {
            $stats[$address->id] = $this->fromAggregateRow($rows->get($address->id));
        }

        return $stats;
    }

    /**
     * Average response times for fixed lookback periods (plus latest).
     *
     * @param  Collection<int, int>|array<int, int>  $addressIds
     * @return array{
     *     labels: list<string>,
     *     keys: list<string>,
     *     values: list<int|null>,
     *     counts: list<int>
     * }
     */
    public function responseTimePeriods(Collection|array $addressIds): array
    {
        $addressIds = collect($addressIds)->map(fn ($id) => (int) $id)->filter()->values();
        $labels = [];
        $keys = [];
        $values = [];
        $counts = [];

        if ($addressIds->isEmpty()) {
            foreach (self::RESPONSE_TIME_PERIODS as $key => $period) {
                $keys[] = $key;
                $labels[] = $period['label'];
                $values[] = null;
                $counts[] = 0;
            }

            return compact('labels', 'keys', 'values', 'counts');
        }

        $maxHours = collect(self::RESPONSE_TIME_PERIODS)
            ->pluck('hours')
            ->filter()
            ->max() ?? 168;

        $snapshots = Snapshot::query()
            ->whereIn('address_id', $addressIds)
            ->where('created_at', '>=', now()->subHours($maxHours))
            ->orderByDesc('created_at')
            ->get(['address_id', 'response_time_ms', 'created_at']);

        $latestIds = Snapshot::query()
            ->selectRaw('MAX(id) as id')
            ->whereIn('address_id', $addressIds)
            ->groupBy('address_id');

        $latestByAddress = Snapshot::query()
            ->whereIn('id', $latestIds)
            ->get(['id', 'address_id', 'response_time_ms']);

        foreach (self::RESPONSE_TIME_PERIODS as $key => $period) {
            $keys[] = $key;
            $labels[] = $period['label'];

            if ($period['hours'] === null) {
                $latestValues = $latestByAddress->pluck('response_time_ms');
                $counts[] = $latestValues->count();
                $values[] = $latestValues->isEmpty()
                    ? null
                    : (int) round((float) $latestValues->avg());

                continue;
            }

            $from = now()->subHours($period['hours']);
            $window = $snapshots->filter(
                fn (Snapshot $snapshot) => $snapshot->created_at !== null
                    && $snapshot->created_at->gte($from)
            );
            $counts[] = $window->count();
            $values[] = $window->isEmpty()
                ? null
                : (int) round((float) $window->avg('response_time_ms'));
        }

        return compact('labels', 'keys', 'values', 'counts');
    }

    /**
     * Multi-series chart data for a site (one series per address + overall).
     *
     * @return array{
     *     labels: list<string>,
     *     keys: list<string>,
     *     series: list<array{id: int|string, label: string, values: list<int|null>, counts: list<int>}>,
     *     has_data: bool
     * }
     */
    public function responseTimeChartForSite(Site $site): array
    {
        $site->loadMissing('addresses');

        $labels = array_column(array_values(self::RESPONSE_TIME_PERIODS), 'label');
        $keys = array_keys(self::RESPONSE_TIME_PERIODS);
        $series = [];
        $hasData = false;

        foreach ($site->addresses as $address) {
            $periods = $this->responseTimePeriods([$address->id]);
            if (collect($periods['values'])->filter(fn ($v) => $v !== null)->isNotEmpty()) {
                $hasData = true;
            }

            $series[] = [
                'id' => $address->id,
                'label' => $address->name ?: $address->endpoint,
                'values' => $periods['values'],
                'counts' => $periods['counts'],
            ];
        }

        if ($site->addresses->count() > 1) {
            $overall = $this->responseTimePeriods($site->addresses->pluck('id'));
            if (collect($overall['values'])->filter(fn ($v) => $v !== null)->isNotEmpty()) {
                $hasData = true;
            }
            array_unshift($series, [
                'id' => 'overall',
                'label' => 'Усі адреси (сер.)',
                'values' => $overall['values'],
                'counts' => $overall['counts'],
            ]);
        }

        return [
            'labels' => $labels,
            'keys' => $keys,
            'series' => $series,
            'has_data' => $hasData,
        ];
    }

    /**
     * @return array{
     *     labels: list<string>,
     *     keys: list<string>,
     *     series: list<array{id: int|string, label: string, values: list<int|null>, counts: list<int>}>,
     *     has_data: bool
     * }
     */
    public function responseTimeChartForAddress(Address $address): array
    {
        $periods = $this->responseTimePeriods([$address->id]);
        $hasData = collect($periods['values'])->filter(fn ($v) => $v !== null)->isNotEmpty();

        return [
            'labels' => $periods['labels'],
            'keys' => $periods['keys'],
            'series' => [[
                'id' => $address->id,
                'label' => $address->name ?: $address->endpoint,
                'values' => $periods['values'],
                'counts' => $periods['counts'],
            ]],
            'has_data' => $hasData,
        ];
    }

    /**
     * @return array{
     *     checks_count: int,
     *     avg_response_time_ms: int|null,
     *     error_count: int,
     *     avg_errors: float|null
     * }
     */
    private function empty(): array
    {
        return [
            'checks_count' => 0,
            'avg_response_time_ms' => null,
            'error_count' => 0,
            'avg_errors' => null,
        ];
    }

    /**
     * @return array{
     *     checks_count: int,
     *     avg_response_time_ms: int|null,
     *     error_count: int,
     *     avg_errors: float|null
     * }
     */
    private function fromAggregateRow(mixed $row): array
    {
        $checksCount = (int) ($row->checks_count ?? 0);

        if ($checksCount === 0) {
            return $this->empty();
        }

        $errorCount = (int) ($row->error_count ?? 0);

        return [
            'checks_count' => $checksCount,
            'avg_response_time_ms' => (int) round((float) $row->avg_response_time_ms),
            'error_count' => $errorCount,
            'avg_errors' => round($errorCount / $checksCount, 2),
        ];
    }

    private function runBucketExpression(): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d %H:%M', created_at)",
            'pgsql' => "to_char(created_at, 'YYYY-MM-DD HH24:MI')",
            default => "DATE_FORMAT(created_at, '%Y-%m-%d %H:%i')",
        };
    }
}
