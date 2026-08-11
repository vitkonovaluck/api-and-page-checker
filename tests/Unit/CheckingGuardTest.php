<?php

namespace Tests\Unit;

use App\Jobs\CheckAddressJob;
use App\Models\Address;
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

        $this->assertFalse($guard->isBusy());

        $ran = false;
        $result = $guard->runManual(function () use ($guard, &$ran) {
            $ran = true;
            $this->assertTrue($guard->isBusy());

            return 'ok';
        });

        $this->assertTrue($ran);
        $this->assertSame('ok', $result);
        $this->assertFalse($guard->isBusy());
    }

    public function test_second_manual_start_is_rejected_while_busy(): void
    {
        Cache::put(CheckingGuard::MANUAL_KEY, true, 60);

        $guard = app(CheckingGuard::class);

        $this->assertNull($guard->runManual(fn () => 'nope'));
        $this->assertTrue($guard->isBusy());
    }

    public function test_pending_check_jobs_mark_busy_when_using_database_queue(): void
    {
        config(['queue.default' => 'database']);

        $site = Site::query()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/health',
        ]);

        CheckAddressJob::dispatch($address);

        $guard = app(CheckingGuard::class);

        $this->assertTrue($guard->hasPendingCheckJobs());
        $this->assertTrue($guard->isBusy());
        $this->assertFalse($guard->tryStartManual());

        DB::table('jobs')->delete();

        $this->assertFalse($guard->isBusy());
    }
}
