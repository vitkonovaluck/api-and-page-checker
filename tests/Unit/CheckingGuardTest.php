<?php

declare(strict_types=1);

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

    public function test_manual_lock_marks_that_site_busy_and_releases(): void
    {
        $site = $this->makeSite();
        $guard = app(CheckingGuard::class);

        $this->assertFalse($guard->isBusy($site->id));

        $ran = false;
        $result = $guard->runManual($site->id, function () use ($guard, $site, &$ran) {
            $ran = true;
            $this->assertTrue($guard->isBusy($site->id));

            return 'ok';
        });

        $this->assertTrue($ran);
        $this->assertSame('ok', $result);
        $this->assertFalse($guard->isBusy($site->id));
    }

    public function test_second_manual_start_is_rejected_only_for_the_busy_site(): void
    {
        $busySite = $this->makeSite('Busy');
        $idleSite = $this->makeSite('Idle');

        Cache::put(CheckingGuard::manualCacheKey($busySite->id), true, 60);

        $guard = app(CheckingGuard::class);

        $this->assertNull($guard->runManual($busySite->id, fn () => 'nope'));
        $this->assertTrue($guard->isBusy($busySite->id));
        $this->assertFalse($guard->isBusy($idleSite->id));
        $this->assertSame('ok', $guard->runManual($idleSite->id, fn () => 'ok'));
    }

    public function test_pending_check_jobs_mark_only_that_site_busy(): void
    {
        config(['queue.default' => 'database']);

        $busySite = $this->makeSite('Busy');
        $idleSite = $this->makeSite('Idle');
        $address = Address::query()->create([
            'site_id' => $busySite->id,
            'endpoint' => '/health',
        ]);

        CheckAddressJob::dispatch($address);

        $guard = app(CheckingGuard::class);

        $this->assertTrue($guard->hasPendingCheckJobs($busySite->id));
        $this->assertTrue($guard->isBusy($busySite->id));
        $this->assertFalse($guard->isBusy($idleSite->id));
        $this->assertFalse($guard->tryStartManual($busySite->id));
        $this->assertSame([$busySite->id], $guard->busySiteIds());
        $this->assertTrue($guard->tryStartManual($idleSite->id));

        DB::table('jobs')->delete();
        $guard->endManual($idleSite->id);

        $this->assertFalse($guard->isBusy($busySite->id));
        $this->assertSame([], $guard->busySiteIds());
    }

    private function makeSite(string $name = 'Demo'): Site
    {
        return Site::query()->create([
            'name' => $name,
            'base_url' => 'https://api.example.com',
        ]);
    }
}
