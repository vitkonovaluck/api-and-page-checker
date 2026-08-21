<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\CheckAddressJob;
use App\Models\Address;
use App\Models\CheckRun;
use App\Models\Site;
use App\Services\CheckingGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckingGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_lock_marks_busy_and_releases(): void
    {
        $guard = app(CheckingGuard::class);

        $this->assertFalse($guard->isBusy(1));

        $ran = false;
        $result = $guard->runManual(1, function () use ($guard, &$ran) {
            $ran = true;
            $this->assertTrue($guard->isBusy(1));
            $this->assertFalse($guard->isBusy(2));

            return 'ok';
        });

        $this->assertTrue($ran);
        $this->assertSame('ok', $result);
        $this->assertFalse($guard->isBusy(1));
    }

    public function test_second_manual_start_is_rejected_while_same_site_is_busy(): void
    {
        Cache::put(CheckingGuard::manualKey(1), true, 60);

        $guard = app(CheckingGuard::class);

        $this->assertNull($guard->runManual(1, fn () => 'nope'));
        $this->assertTrue($guard->isBusy(1));
        $this->assertSame('ok', $guard->runManual(2, fn () => 'ok'));
    }

    public function test_pending_check_jobs_mark_only_that_site_busy_when_using_database_queue(): void
    {
        config(['queue.default' => 'database']);

        $busySite = Site::factory()->create([
            'name' => 'Busy',
            'base_url' => 'https://busy.example.com',
        ]);
        $idleSite = Site::factory()->create([
            'name' => 'Idle',
            'base_url' => 'https://idle.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $busySite->id,
            'endpoint' => '/health',
        ]);

        CheckAddressJob::dispatch($address);

        $guard = app(CheckingGuard::class);

        $this->assertTrue($guard->hasPendingCheckJobs($busySite->id));
        $this->assertFalse($guard->hasPendingCheckJobs($idleSite->id));
        $this->assertTrue($guard->isBusy($busySite->id));
        $this->assertFalse($guard->isBusy($idleSite->id));
        $this->assertSame([$busySite->id], $guard->busySiteIds());
        $this->assertFalse($guard->tryStartManual($busySite->id));
        $this->assertTrue($guard->tryStartManual($idleSite->id));

        DB::table('jobs')->delete();

        $this->assertFalse($guard->isBusy($busySite->id));
        $this->assertSame([], $guard->busySiteIds());
    }

    public function test_forget_pending_jobs_removes_only_jobs_for_that_check_run(): void
    {
        config(['queue.default' => 'database']);

        $site = Site::factory()->create([
            'name' => 'Busy',
            'base_url' => 'https://busy.example.com',
        ]);
        $other = Site::factory()->create([
            'name' => 'Other',
            'base_url' => 'https://other.example.com',
        ]);
        $first = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/one',
        ]);
        $second = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/two',
        ]);
        $otherAddress = Address::query()->create([
            'site_id' => $other->id,
            'endpoint' => '/health',
        ]);

        $run = CheckRun::start($site, CheckRun::SOURCE_MANUAL, 2);
        $keptRun = CheckRun::start($other, CheckRun::SOURCE_MANUAL, 1);
        CheckAddressJob::dispatch($first, $run->id);
        CheckAddressJob::dispatch($second, $run->id);
        CheckAddressJob::dispatch($otherAddress, $keptRun->id);

        $guard = app(CheckingGuard::class);
        $deleted = $guard->forgetPendingJobsForRun((int) $site->id, (int) $run->id);

        $this->assertSame(2, $deleted);
        $this->assertFalse($guard->hasPendingCheckJobs($site->id));
        $this->assertTrue($guard->hasPendingCheckJobs($other->id));
    }

    public function test_cancelled_run_flag_is_honored_and_missing_runs_are_cancelled(): void
    {
        $site = Site::factory()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $run = CheckRun::start($site, CheckRun::SOURCE_MANUAL, 1);
        $guard = app(CheckingGuard::class);

        $this->assertFalse($guard->isRunCancelled((int) $run->id));

        $guard->cancelRun((int) $run->id);

        $this->assertTrue($guard->isRunCancelled((int) $run->id));
        $this->assertTrue($guard->isRunCancelled(999_999));
    }
}
