<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Actions\StopManualCheckRunAction;
use App\Jobs\CheckAddressJob;
use App\Models\Address;
use App\Models\CheckRun;
use App\Models\Site;
use App\Models\Snapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StopManualCheckRunActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_finds_an_in_progress_manual_run(): void
    {
        $site = $this->makeSite();
        $this->makeAddresses($site);
        $run = CheckRun::start($site, CheckRun::SOURCE_MANUAL, 2);

        $found = app(StopManualCheckRunAction::class)->find($site);

        $this->assertNotNull($found);
        $this->assertTrue($found->is($run));
    }

    public function test_does_not_find_a_completed_manual_run(): void
    {
        $site = $this->makeSite();
        $this->makeAddresses($site);
        CheckRun::start($site, CheckRun::SOURCE_MANUAL);

        $this->assertNull(app(StopManualCheckRunAction::class)->find($site));
    }

    public function test_does_not_find_an_in_progress_scheduled_run(): void
    {
        $site = $this->makeSite();
        $this->makeAddresses($site);
        CheckRun::start($site, CheckRun::SOURCE_SCHEDULE, 2);

        $this->assertNull(app(StopManualCheckRunAction::class)->find($site));
    }

    public function test_execute_deletes_only_the_in_progress_manual_run(): void
    {
        $site = $this->makeSite();
        [$first, $second] = $this->makeAddresses($site);

        $previous = CheckRun::start($site, CheckRun::SOURCE_SCHEDULE);
        $keptFirst = $this->createSnapshot($first, $previous->id, '2026-08-21 09:00:00');
        $keptSecond = $this->createSnapshot($second, $previous->id, '2026-08-21 09:00:01');

        $manual = CheckRun::start($site, CheckRun::SOURCE_MANUAL, 2);
        $this->createSnapshot($first, $manual->id, '2026-08-21 11:00:00');

        $first->forceFill(['last_checked_at' => '2026-08-21 11:00:00'])->save();
        $second->forceFill(['last_checked_at' => '2026-08-21 09:00:01'])->save();

        $deleted = app(StopManualCheckRunAction::class)->execute($site);

        $this->assertSame(1, $deleted);
        $this->assertModelMissing($manual);
        $this->assertModelExists($previous);
        $this->assertModelExists($keptFirst);
        $this->assertModelExists($keptSecond);
        $this->assertTrue($first->fresh()?->last_checked_at?->equalTo($keptFirst->created_at));
        $this->assertTrue($second->fresh()?->last_checked_at?->equalTo($keptSecond->created_at));
    }

    public function test_execute_removes_pending_jobs_for_the_stopped_run_only(): void
    {
        config(['queue.default' => 'database']);

        $site = $this->makeSite();
        $other = $this->makeSite();
        [$first, $second] = $this->makeAddresses($site);
        [$otherAddress] = $this->makeAddresses($other, '/keep');

        $manual = CheckRun::start($site, CheckRun::SOURCE_MANUAL, 2);
        CheckAddressJob::dispatch($first, $manual->id);
        CheckAddressJob::dispatch($second, $manual->id);

        $otherRun = CheckRun::start($other, CheckRun::SOURCE_MANUAL, 1);
        CheckAddressJob::dispatch($otherAddress, $otherRun->id);

        app(StopManualCheckRunAction::class)->execute($site);

        $this->assertSame(0, $this->pendingJobCount($site));
        $this->assertSame(1, $this->pendingJobCount($other));
        $this->assertModelMissing($manual);
        $this->assertModelExists($otherRun);
    }

    public function test_stoppable_site_ids_include_only_in_progress_manual_runs(): void
    {
        $manualSite = $this->makeSite();
        $scheduleSite = $this->makeSite();
        $this->makeAddresses($manualSite);
        $this->makeAddresses($scheduleSite);

        CheckRun::start($manualSite, CheckRun::SOURCE_MANUAL, 2);
        CheckRun::start($scheduleSite, CheckRun::SOURCE_SCHEDULE, 2);

        $ids = app(StopManualCheckRunAction::class)->stoppableSiteIds([
            $manualSite->id,
            $scheduleSite->id,
        ]);

        $this->assertSame([$manualSite->id], $ids);
    }

    private function makeSite(): Site
    {
        return Site::factory()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
    }

    /**
     * @return list<Address>
     */
    private function makeAddresses(Site $site, string $firstEndpoint = '/one'): array
    {
        $addresses = [
            Address::query()->create([
                'site_id' => $site->id,
                'endpoint' => $firstEndpoint,
            ]),
        ];

        if ($firstEndpoint === '/one') {
            $addresses[] = Address::query()->create([
                'site_id' => $site->id,
                'endpoint' => '/two',
            ]);
        }

        return $addresses;
    }

    private function createSnapshot(Address $address, int $checkRunId, string $createdAt): Snapshot
    {
        $snapshot = Snapshot::query()->create([
            'address_id' => $address->id,
            'check_run_id' => $checkRunId,
            'status_code' => 200,
            'headers' => [],
            'body' => '{}',
            'body_hash' => hash('sha256', '{}'),
            'response_time_ms' => 10,
        ]);

        Snapshot::query()->whereKey($snapshot->id)->update(['created_at' => $createdAt]);

        return $snapshot->refresh();
    }

    private function pendingJobCount(Site $site): int
    {
        return DB::table('jobs')
            ->where('queue', Site::checkQueueName((int) $site->id))
            ->count();
    }
}
