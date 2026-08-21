<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Actions\DeleteLatestManualCheckRunAction;
use App\Models\Address;
use App\Models\CheckRun;
use App\Models\Site;
use App\Models\Snapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteLatestManualCheckRunActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_finds_a_completed_manual_pass_of_all_addresses(): void
    {
        $site = $this->makeSite();
        [$first, $second] = $this->makeAddresses($site);

        $run = CheckRun::start($site, CheckRun::SOURCE_MANUAL);
        $this->createSnapshot($first, $run->id, '2026-08-21 10:00:00');
        $this->createSnapshot($second, $run->id, '2026-08-21 10:00:01');

        $found = app(DeleteLatestManualCheckRunAction::class)->find($site);

        $this->assertNotNull($found);
        $this->assertTrue($found->is($run));
    }

    public function test_does_not_find_a_single_address_manual_check_on_a_multi_address_site(): void
    {
        $site = $this->makeSite();
        [$first] = $this->makeAddresses($site);

        $run = CheckRun::start($site, CheckRun::SOURCE_MANUAL);
        $this->createSnapshot($first, $run->id, '2026-08-21 10:00:00');

        $this->assertNull(app(DeleteLatestManualCheckRunAction::class)->find($site));
    }

    public function test_finds_a_manual_pass_on_a_single_address_site(): void
    {
        $site = $this->makeSite();
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/one',
        ]);

        $run = CheckRun::start($site, CheckRun::SOURCE_MANUAL);
        $this->createSnapshot($address, $run->id, '2026-08-21 10:00:00');

        $found = app(DeleteLatestManualCheckRunAction::class)->find($site);

        $this->assertNotNull($found);
        $this->assertTrue($found->is($run));
    }

    public function test_does_not_find_an_in_progress_manual_run(): void
    {
        $site = $this->makeSite();
        [$first, $second] = $this->makeAddresses($site);

        $run = CheckRun::start($site, CheckRun::SOURCE_MANUAL, 2);
        $this->createSnapshot($first, $run->id, '2026-08-21 10:00:00');
        $this->createSnapshot($second, $run->id, '2026-08-21 10:00:01');

        $this->assertNull(app(DeleteLatestManualCheckRunAction::class)->find($site));
    }

    public function test_does_not_find_a_manual_run_when_a_later_schedule_run_exists(): void
    {
        $site = $this->makeSite();
        [$first, $second] = $this->makeAddresses($site);

        $manual = CheckRun::start($site, CheckRun::SOURCE_MANUAL);
        $this->createSnapshot($first, $manual->id, '2026-08-21 10:00:00');
        $this->createSnapshot($second, $manual->id, '2026-08-21 10:00:01');

        $schedule = CheckRun::start($site, CheckRun::SOURCE_SCHEDULE);
        $this->createSnapshot($first, $schedule->id, '2026-08-21 10:05:00');
        $this->createSnapshot($second, $schedule->id, '2026-08-21 10:05:01');

        $this->assertNull(app(DeleteLatestManualCheckRunAction::class)->find($site));
    }

    public function test_deletes_last_manual_pass_snapshots_and_restores_last_checked_at(): void
    {
        $site = $this->makeSite();
        [$first, $second] = $this->makeAddresses($site);

        $previous = CheckRun::start($site, CheckRun::SOURCE_SCHEDULE);
        $previousFirst = $this->createSnapshot($first, $previous->id, '2026-08-21 09:00:00');
        $previousSecond = $this->createSnapshot($second, $previous->id, '2026-08-21 09:00:01');

        $manual = CheckRun::start($site, CheckRun::SOURCE_MANUAL);
        $this->createSnapshot($first, $manual->id, '2026-08-21 11:00:00');
        $this->createSnapshot($second, $manual->id, '2026-08-21 11:00:01');

        $first->forceFill(['last_checked_at' => '2026-08-21 11:00:00'])->save();
        $second->forceFill(['last_checked_at' => '2026-08-21 11:00:01'])->save();

        $deleted = app(DeleteLatestManualCheckRunAction::class)->execute($site);

        $this->assertSame(2, $deleted);
        $this->assertModelMissing($manual);
        $this->assertModelExists($previous);
        $this->assertModelExists($previousFirst);
        $this->assertModelExists($previousSecond);
        $this->assertTrue($first->fresh()?->last_checked_at?->equalTo($previousFirst->created_at));
        $this->assertTrue($second->fresh()?->last_checked_at?->equalTo($previousSecond->created_at));
    }

    public function test_clears_last_checked_at_when_no_previous_snapshots_remain(): void
    {
        $site = $this->makeSite();
        [$first, $second] = $this->makeAddresses($site);

        $manual = CheckRun::start($site, CheckRun::SOURCE_MANUAL);
        $this->createSnapshot($first, $manual->id, '2026-08-21 11:00:00');
        $this->createSnapshot($second, $manual->id, '2026-08-21 11:00:01');

        $first->forceFill(['last_checked_at' => '2026-08-21 11:00:00'])->save();
        $second->forceFill(['last_checked_at' => '2026-08-21 11:00:01'])->save();

        app(DeleteLatestManualCheckRunAction::class)->execute($site);

        $this->assertNull($first->fresh()?->last_checked_at);
        $this->assertNull($second->fresh()?->last_checked_at);
        $this->assertSame(0, $site->snapshots()->count());
    }

    public function test_does_not_delete_another_sites_check_run(): void
    {
        $site = $this->makeSite();
        $other = $this->makeSite();
        [$first, $second] = $this->makeAddresses($site);
        [$otherAddress] = $this->makeAddresses($other, '/keep');

        $manual = CheckRun::start($site, CheckRun::SOURCE_MANUAL);
        $this->createSnapshot($first, $manual->id, '2026-08-21 11:00:00');
        $this->createSnapshot($second, $manual->id, '2026-08-21 11:00:01');

        $otherRun = CheckRun::start($other, CheckRun::SOURCE_MANUAL);
        $otherSnapshot = $this->createSnapshot($otherAddress, $otherRun->id, '2026-08-21 11:00:00');

        app(DeleteLatestManualCheckRunAction::class)->execute($site);

        $this->assertModelExists($otherRun);
        $this->assertModelExists($otherSnapshot);
    }

    public function test_deletes_snapshots_in_configured_chunks(): void
    {
        config(['checking.snapshot_delete_chunk' => 1]);

        $site = $this->makeSite();
        [$first, $second] = $this->makeAddresses($site);

        $run = CheckRun::start($site, CheckRun::SOURCE_MANUAL);
        $this->createSnapshot($first, $run->id, '2026-08-21 11:00:00');
        $this->createSnapshot($second, $run->id, '2026-08-21 11:00:01');

        $deleted = app(DeleteLatestManualCheckRunAction::class)->execute($site);

        $this->assertSame(2, $deleted);
        $this->assertModelMissing($run);
        $this->assertSame(0, $site->snapshots()->count());
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
}
