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
