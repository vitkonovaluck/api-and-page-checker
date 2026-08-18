<?php

namespace App\Services;

use App\Enums\ResponseTimeMetric;
use App\Models\Address;
use App\Models\CheckRun;
use App\Models\Site;
use App\Models\Snapshot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CheckStats
{
    /**
     * Selectable lookback windows for response-time history charts.
     *
     * @var array<string, array{label: string, hours: int}>
     */
    public const RESPONSE_TIME_PERIODS = [
        'latest' => ['label' => 'Останній час', 'hours' => 1],
        '6h' => ['label' => '6 год', 'hours' => 6],
        '12h' => ['label' => '12 год', 'hours' => 12],
        '24h' => ['label' => '24 год', 'hours' => 24],
        '48h' => ['label' => '48 год', 'hours' => 48],
        '96h' => ['label' => '96 год', 'hours' => 96],
        '1w' => ['label' => '1 тиждень', 'hours' => 168],
    ];

    public const DEFAULT_RESPONSE_TIME_PERIOD = '24h';

    /**
     * @return array{
     *     checks_count: int,
     *     avg_response_time_ms: int|null,
     *     error_count: int,
     *     avg_errors: float|null
     * }
     */
    public function forSnapshots(Collection $snapshots, ResponseTimeMetric $metric = ResponseTimeMetric::Total): array
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
            'avg_response_time_ms' => $this->averageTimeMs($snapshots, $metric),
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
    public function forAddress(Address $address, ResponseTimeMetric $metric = ResponseTimeMetric::Total): array
    {
        $row = Snapshot::query()
            ->where('address_id', $address->id)
            ->selectRaw('COUNT(*) as checks_count')
            ->selectRaw($this->avgTimeSelectRaw($metric))
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
     *     avg_response_time_per_run_ms: int|null,
     *     avg_latest_response_time_ms: int|null,
     *     latest_checks_count: int
     * }
     */
    public function forSite(Site $site, bool $scheduledOnly = false, ResponseTimeMetric $metric = ResponseTimeMetric::Total): array
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
                'avg_latest_response_time_ms' => null,
                'latest_checks_count' => 0,
            ]);
        }

        $row = Snapshot::query()
            ->whereIn('address_id', $addressIds)
            ->selectRaw('COUNT(*) as checks_count')
            ->selectRaw($this->avgTimeSelectRaw($metric))
            ->selectRaw('SUM(CASE WHEN error_message IS NOT NULL AND error_message != \'\' THEN 1 ELSE 0 END) as error_count')
            ->first();

        $base = $this->fromAggregateRow($row);

        $runBucketsQuery = Snapshot::query()
            ->whereIn('address_id', $addressIds);

        if ($scheduledOnly) {
            $runBucketsQuery->where(function ($query) {
                $query->whereNull('check_run_id')
                    ->orWhereIn('check_run_id', function ($sub) {
                        $sub->select('id')
                            ->from('check_runs')
                            ->where('source', CheckRun::SOURCE_SCHEDULE);
                    });
            });
        }

        $runBuckets = $runBucketsQuery
            ->selectRaw($this->runGroupExpression().' as run_bucket')
            ->selectRaw($this->avgTimeSelectRaw($metric))
            ->selectRaw('SUM(CASE WHEN error_message IS NOT NULL AND error_message != \'\' THEN 1 ELSE 0 END) as error_count')
            ->groupBy('run_bucket')
            ->get();

        $runsCount = $runBuckets->count();

        $latestSnapshotIds = Snapshot::query()
            ->whereIn('address_id', $addressIds)
            ->selectRaw('MAX(id) as id')
            ->groupBy('address_id')
            ->pluck('id');

        $latestChecksCount = $latestSnapshotIds->count();
        $avgLatestResponseTimeMs = null;

        if ($latestChecksCount > 0) {
            $latestAvg = Snapshot::query()
                ->whereIn('id', $latestSnapshotIds)
                ->selectRaw($this->avgTimeSelectRaw($metric))
                ->value('avg_response_time_ms');

            $avgLatestResponseTimeMs = $latestAvg === null
                ? null
                : (int) round((float) $latestAvg);
        }

        return array_merge($base, [
            'runs_count' => $runsCount,
            'avg_errors_per_run' => $runsCount > 0
                ? round((float) $runBuckets->avg('error_count'), 2)
                : null,
            'avg_response_time_per_run_ms' => $this->averageFromValues(
                $runBuckets->pluck('avg_response_time_ms')
            ),
            'avg_latest_response_time_ms' => $avgLatestResponseTimeMs,
            'latest_checks_count' => $latestChecksCount,
        ]);
    }

    /**
     * Snapshots with errors for a site. When $scheduledOnly is true, only
     * schedule-enabled addresses and schedule (or legacy) runs are included —
     * matching «Сер. помилок / запуск».
     *
     * @return Builder<Snapshot>
     */
    public function errorSnapshotsForSite(Site $site, bool $scheduledOnly = true): Builder
    {
        $addressQuery = $site->addresses();
        if ($scheduledOnly) {
            $addressQuery->where('schedule_enabled', true);
        }

        $addressIds = $addressQuery->pluck('id');

        if ($addressIds->isEmpty()) {
            return Snapshot::query()->whereRaw('0 = 1');
        }

        $query = Snapshot::query()
            ->with(['address', 'checkRun'])
            ->whereIn('address_id', $addressIds)
            ->whereNotNull('error_message')
            ->where('error_message', '!=', '')
            ->orderByDesc('id');

        if ($scheduledOnly) {
            $query->where(function ($inner) {
                $inner->whereNull('check_run_id')
                    ->orWhereIn('check_run_id', function ($sub) {
                        $sub->select('id')
                            ->from('check_runs')
                            ->where('source', CheckRun::SOURCE_SCHEDULE);
                    });
            });
        }

        return $query;
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
    public function forAddresses(Collection $addresses, ResponseTimeMetric $metric = ResponseTimeMetric::Total): array
    {
        if ($addresses->isEmpty()) {
            return [];
        }

        $rows = Snapshot::query()
            ->whereIn('address_id', $addresses->pluck('id'))
            ->selectRaw('address_id')
            ->selectRaw('COUNT(*) as checks_count')
            ->selectRaw($this->avgTimeSelectRaw($metric))
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

    public function normalizePeriod(?string $period): string
    {
        if ($period !== null && array_key_exists($period, self::RESPONSE_TIME_PERIODS)) {
            return $period;
        }

        return self::DEFAULT_RESPONSE_TIME_PERIOD;
    }

    /**
     * Time-series of average response time and TTFB across all site addresses for one period.
     *
     * @return array{
     *     period: string,
     *     period_label: string,
     *     periods: array<string, array{label: string, hours: int}>,
     *     labels: list<string>,
     *     values: list<int>,
     *     ttfb_values: list<int|null>,
     *     counts: list<int>,
     *     series: list<array{key: string, label: string, values: list<int|null>}>,
     *     avg_response_time_ms: int|null,
     *     avg_ttfb_ms: int|null,
     *     points_count: int,
     *     checks_count: int,
     *     has_data: bool,
     *     mode: 'site'|'address'
     * }
     */
    public function responseTimeChartForSite(Site $site, ?string $period = null): array
    {
        $site->loadMissing('addresses');

        return $this->responseTimeSeries(
            addressIds: $site->addresses->pluck('id'),
            period: $period,
            averageAcrossAddresses: true,
            mode: 'site',
        );
    }

    /**
     * Time-series of a single address response time and TTFB for one period.
     *
     * @return array{
     *     period: string,
     *     period_label: string,
     *     periods: array<string, array{label: string, hours: int}>,
     *     labels: list<string>,
     *     values: list<int>,
     *     ttfb_values: list<int|null>,
     *     counts: list<int>,
     *     series: list<array{key: string, label: string, values: list<int|null>}>,
     *     avg_response_time_ms: int|null,
     *     avg_ttfb_ms: int|null,
     *     points_count: int,
     *     checks_count: int,
     *     has_data: bool,
     *     mode: 'site'|'address'
     * }
     */
    public function responseTimeChartForAddress(Address $address, ?string $period = null): array
    {
        return $this->responseTimeSeries(
            addressIds: [$address->id],
            period: $period,
            averageAcrossAddresses: false,
            mode: 'address',
        );
    }

    /**
     * @param  Collection<int, int>|array<int, int>  $addressIds
     * @return array{
     *     period: string,
     *     period_label: string,
     *     periods: array<string, array{label: string, hours: int}>,
     *     labels: list<string>,
     *     values: list<int>,
     *     ttfb_values: list<int|null>,
     *     counts: list<int>,
     *     series: list<array{key: string, label: string, values: list<int|null>}>,
     *     avg_response_time_ms: int|null,
     *     avg_ttfb_ms: int|null,
     *     points_count: int,
     *     checks_count: int,
     *     has_data: bool,
     *     mode: 'site'|'address'
     * }
     */
    private function responseTimeSeries(
        Collection|array $addressIds,
        ?string $period,
        bool $averageAcrossAddresses,
        string $mode,
    ): array {
        $periodKey = $this->normalizePeriod($period);
        $hours = self::RESPONSE_TIME_PERIODS[$periodKey]['hours'];
        $periodLabel = self::RESPONSE_TIME_PERIODS[$periodKey]['label'];
        $addressIds = collect($addressIds)->map(fn ($id) => (int) $id)->filter()->values();

        if ($addressIds->isEmpty()) {
            return $this->emptyResponseTimeChart($periodKey, $periodLabel, $mode);
        }

        $from = now()->subHours($hours);
        $points = $averageAcrossAddresses
            ? $this->siteChartPoints($addressIds, $from)
            : $this->addressChartPoints($addressIds, $from);

        return $this->responseTimeChartPayload($periodKey, $periodLabel, $mode, $points);
    }

    /**
     * @return array{
     *     period: string,
     *     period_label: string,
     *     periods: array<string, array{label: string, hours: int}>,
     *     labels: list<string>,
     *     values: list<int>,
     *     ttfb_values: list<int|null>,
     *     counts: list<int>,
     *     series: list<array{key: string, label: string, values: list<int|null>}>,
     *     avg_response_time_ms: int|null,
     *     avg_ttfb_ms: int|null,
     *     points_count: int,
     *     checks_count: int,
     *     has_data: bool,
     *     mode: 'site'|'address'
     * }
     */
    private function emptyResponseTimeChart(string $periodKey, string $periodLabel, string $mode): array
    {
        return $this->responseTimeChartPayload($periodKey, $periodLabel, $mode, [
            'labels' => [],
            'values' => [],
            'ttfb_values' => [],
            'counts' => [],
            'avg_response_time_ms' => null,
            'avg_ttfb_ms' => null,
            'checks_count' => 0,
        ]);
    }

    /**
     * @param  array{
     *     labels: list<string>,
     *     values: list<int>,
     *     ttfb_values: list<int|null>,
     *     counts: list<int>,
     *     avg_response_time_ms: int|null,
     *     avg_ttfb_ms: int|null,
     *     checks_count: int
     * }  $points
     * @return array{
     *     period: string,
     *     period_label: string,
     *     periods: array<string, array{label: string, hours: int}>,
     *     labels: list<string>,
     *     values: list<int>,
     *     ttfb_values: list<int|null>,
     *     counts: list<int>,
     *     series: list<array{key: string, label: string, values: list<int|null>}>,
     *     avg_response_time_ms: int|null,
     *     avg_ttfb_ms: int|null,
     *     points_count: int,
     *     checks_count: int,
     *     has_data: bool,
     *     mode: 'site'|'address'
     * }
     */
    private function responseTimeChartPayload(string $periodKey, string $periodLabel, string $mode, array $points): array
    {
        $values = $points['values'];
        $ttfbValues = $points['ttfb_values'];
        $pointsCount = count($values);

        return [
            'period' => $periodKey,
            'period_label' => $periodLabel,
            'periods' => self::RESPONSE_TIME_PERIODS,
            'labels' => $points['labels'],
            'values' => $values,
            'ttfb_values' => $ttfbValues,
            'counts' => $points['counts'],
            'series' => [
                [
                    'key' => ResponseTimeMetric::Total->value,
                    'label' => ResponseTimeMetric::Total->toggleLabel(),
                    'values' => $values,
                ],
                [
                    'key' => ResponseTimeMetric::Ttfb->value,
                    'label' => ResponseTimeMetric::Ttfb->toggleLabel(),
                    'values' => $ttfbValues,
                ],
            ],
            'avg_response_time_ms' => $points['avg_response_time_ms'],
            'avg_ttfb_ms' => $points['avg_ttfb_ms'],
            'points_count' => $pointsCount,
            'checks_count' => $points['checks_count'],
            'has_data' => $pointsCount > 0,
            'mode' => $mode,
        ];
    }

    /**
     * @param  Collection<int, int>  $addressIds
     * @return array{
     *     labels: list<string>,
     *     values: list<int>,
     *     ttfb_values: list<int|null>,
     *     counts: list<int>,
     *     avg_response_time_ms: int|null,
     *     avg_ttfb_ms: int|null,
     *     checks_count: int
     * }
     */
    private function siteChartPoints(Collection $addressIds, Carbon $from): array
    {
        $rows = Snapshot::query()
            ->whereIn('address_id', $addressIds)
            ->where('created_at', '>=', $from)
            ->selectRaw($this->runGroupExpression().' as bucket')
            ->selectRaw('MIN(created_at) as bucket_at')
            ->selectRaw($this->avgTimeSelectRaw(ResponseTimeMetric::Total))
            ->selectRaw($this->avgTimeSelectRaw(ResponseTimeMetric::Ttfb, 'avg_ttfb_ms'))
            ->selectRaw('COUNT(*) as checks_count')
            ->selectRaw('SUM(CASE WHEN '.$this->timeExpression(ResponseTimeMetric::Ttfb).' IS NOT NULL THEN 1 ELSE 0 END) as ttfb_checks_count')
            ->groupBy('bucket')
            ->orderBy('bucket_at')
            ->get();

        $labels = [];
        $values = [];
        $ttfbValues = [];
        $counts = [];
        $weightedSum = 0;
        $ttfbWeightedSum = 0;
        $checksCount = 0;
        $ttfbChecksCount = 0;

        foreach ($rows as $row) {
            if ($row->avg_response_time_ms === null) {
                continue;
            }

            $avg = (int) round((float) $row->avg_response_time_ms);
            $count = (int) $row->checks_count;
            $labels[] = $this->formatBucketAtLabel($row->bucket_at);
            $values[] = $avg;
            $counts[] = $count;
            $weightedSum += $avg * $count;
            $checksCount += $count;

            $bucketTtfbCount = (int) $row->ttfb_checks_count;
            if ($row->avg_ttfb_ms === null || $bucketTtfbCount === 0) {
                $ttfbValues[] = null;

                continue;
            }

            $avgTtfb = (int) round((float) $row->avg_ttfb_ms);
            $ttfbValues[] = $avgTtfb;
            $ttfbWeightedSum += $avgTtfb * $bucketTtfbCount;
            $ttfbChecksCount += $bucketTtfbCount;
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'ttfb_values' => $ttfbValues,
            'counts' => $counts,
            'avg_response_time_ms' => $checksCount > 0 ? (int) round($weightedSum / $checksCount) : null,
            'avg_ttfb_ms' => $ttfbChecksCount > 0 ? (int) round($ttfbWeightedSum / $ttfbChecksCount) : null,
            'checks_count' => $checksCount,
        ];
    }

    /**
     * @param  Collection<int, int>  $addressIds
     * @return array{
     *     labels: list<string>,
     *     values: list<int>,
     *     ttfb_values: list<int|null>,
     *     counts: list<int>,
     *     avg_response_time_ms: int|null,
     *     avg_ttfb_ms: int|null,
     *     checks_count: int
     * }
     */
    private function addressChartPoints(Collection $addressIds, Carbon $from): array
    {
        $snapshots = Snapshot::query()
            ->whereIn('address_id', $addressIds)
            ->where('created_at', '>=', $from)
            ->orderBy('created_at')
            ->get(['response_time_ms', 'timing', 'created_at']);

        $labels = [];
        $values = [];
        $ttfbValues = [];
        $counts = [];
        $weightedSum = 0;
        $ttfbWeightedSum = 0;
        $checksCount = 0;
        $ttfbChecksCount = 0;

        foreach ($snapshots as $snapshot) {
            $total = $snapshot->timeMs(ResponseTimeMetric::Total);
            if ($total === null) {
                continue;
            }

            $ttfb = $snapshot->timeMs(ResponseTimeMetric::Ttfb);
            $labels[] = $snapshot->created_at?->format('d.m H:i') ?? '';
            $values[] = $total;
            $ttfbValues[] = $ttfb;
            $counts[] = 1;
            $weightedSum += $total;
            $checksCount++;

            if ($ttfb === null) {
                continue;
            }

            $ttfbWeightedSum += $ttfb;
            $ttfbChecksCount++;
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'ttfb_values' => $ttfbValues,
            'counts' => $counts,
            'avg_response_time_ms' => $checksCount > 0 ? (int) round($weightedSum / $checksCount) : null,
            'avg_ttfb_ms' => $ttfbChecksCount > 0 ? (int) round($ttfbWeightedSum / $ttfbChecksCount) : null,
            'checks_count' => $checksCount,
        ];
    }

    private function formatBucketAtLabel(mixed $bucketAt): string
    {
        try {
            return Carbon::parse($bucketAt)->format('d.m H:i');
        } catch (\Throwable) {
            return (string) $bucketAt;
        }
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
            'avg_response_time_ms' => $this->nullableRoundedAvg($row->avg_response_time_ms ?? null),
            'error_count' => $errorCount,
            'avg_errors' => round($errorCount / $checksCount, 2),
        ];
    }

    /**
     * @param  Collection<int, Snapshot>  $snapshots
     */
    private function averageTimeMs(Collection $snapshots, ResponseTimeMetric $metric): ?int
    {
        $times = $snapshots
            ->map(fn (Snapshot $snapshot) => $snapshot->timeMs($metric))
            ->filter(fn (?int $value) => $value !== null);

        if ($times->isEmpty()) {
            return null;
        }

        return (int) round((float) $times->avg());
    }

    private function nullableRoundedAvg(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) round((float) $value);
    }

    private function averageFromValues(Collection $values): ?int
    {
        $numeric = $values->filter(fn (mixed $value) => $value !== null && $value !== '');

        if ($numeric->isEmpty()) {
            return null;
        }

        return (int) round((float) $numeric->avg());
    }

    private function avgTimeSelectRaw(ResponseTimeMetric $metric, string $alias = 'avg_response_time_ms'): string
    {
        return 'AVG('.$this->timeExpression($metric).') as '.$alias;
    }

    private function timeExpression(ResponseTimeMetric $metric): string
    {
        if ($metric === ResponseTimeMetric::Total) {
            return 'response_time_ms';
        }

        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "CAST(json_extract(timing, '$.ttfb_ms') AS INTEGER)",
            'pgsql' => "(timing->>'ttfb_ms')::int",
            default => "CAST(JSON_UNQUOTE(JSON_EXTRACT(timing, '$.ttfb_ms')) AS UNSIGNED)",
        };
    }

    /**
     * Group snapshots by logical check run when present; otherwise by minute
     * of created_at (legacy rows without check_run_id).
     */
    private function runGroupExpression(): string
    {
        $minuteBucket = $this->runBucketExpression();
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "CASE WHEN check_run_id IS NOT NULL THEN 'run:' || check_run_id ELSE {$minuteBucket} END",
            'pgsql' => "CASE WHEN check_run_id IS NOT NULL THEN 'run:' || check_run_id::text ELSE {$minuteBucket} END",
            default => "CASE WHEN check_run_id IS NOT NULL THEN CONCAT('run:', check_run_id) ELSE {$minuteBucket} END",
        };
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
