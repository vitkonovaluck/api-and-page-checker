<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CheckAddressJob;
use App\Jobs\DeleteLatestManualCheckRunJob;
use App\Livewire\Sites\Index;
use App\Livewire\Sites\Show;
use App\Models\Address;
use App\Models\CheckRun;
use App\Models\Site;
use App\Models\Snapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class StopManualCheckRunTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsUser();
    }

    public function test_site_show_can_stop_an_in_progress_manual_check_and_delete_only_that_run(): void
    {
        $site = $this->makeSite();
        [$first, $second] = $this->makeAddresses($site);

        $previous = CheckRun::start($site, CheckRun::SOURCE_SCHEDULE);
        $keptFirst = $this->createSnapshot($first, $previous->id, '2026-08-21 09:00:00');
        $keptSecond = $this->createSnapshot($second, $previous->id, '2026-08-21 09:00:01');

        $manual = CheckRun::start($site, CheckRun::SOURCE_MANUAL, 2);
        $this->createSnapshot($first, $manual->id, '2026-08-21 11:00:00');

        Livewire::test(Show::class, ['site' => $site])
            ->assertSee('Зупинити перевірку')
            ->call('stopManualCheckRun')
            ->assertRedirect("/sites/{$site->id}")
            ->assertSessionHas('success', 'Перевірку зупинено. Дані цього проходу видаляються.');

        $this->assertModelMissing($manual);
        $this->assertModelExists($previous);
        $this->assertModelExists($keptFirst);
        $this->assertModelExists($keptSecond);
    }

    public function test_site_show_queues_deletion_after_stopping_the_run(): void
    {
        Queue::fake();

        $site = $this->makeSite();
        [$first, $second] = $this->makeAddresses($site);

        $manual = CheckRun::start($site, CheckRun::SOURCE_MANUAL, 2);
        $this->createSnapshot($first, $manual->id, '2026-08-21 11:00:00');
        $this->createSnapshot($second, $manual->id, '2026-08-21 11:00:01');

        Livewire::test(Show::class, ['site' => $site])
            ->call('stopManualCheckRun')
            ->assertRedirect("/sites/{$site->id}")
            ->assertSessionHas('success', 'Перевірку зупинено. Дані цього проходу видаляються.');

        Queue::assertPushedOn(Site::checkQueueName((int) $site->id), DeleteLatestManualCheckRunJob::class);
        $this->assertModelExists($manual);
        $this->assertSame(0, $manual->fresh()?->remaining_jobs);
    }

    public function test_stop_button_is_hidden_when_the_running_check_is_scheduled(): void
    {
        $site = $this->makeSite();
        $this->makeAddresses($site);
        CheckRun::start($site, CheckRun::SOURCE_SCHEDULE, 2);

        Livewire::test(Show::class, ['site' => $site])
            ->assertDontSee('Зупинити перевірку')
            ->call('stopManualCheckRun')
            ->assertRedirect("/sites/{$site->id}")
            ->assertSessionHas('error', 'Немає активної ручної перевірки для зупинки.');
    }

    public function test_completed_manual_run_cannot_be_stopped(): void
    {
        $site = $this->makeSite();
        [$first, $second] = $this->makeAddresses($site);

        $run = CheckRun::start($site, CheckRun::SOURCE_MANUAL);
        $this->createSnapshot($first, $run->id, '2026-08-21 11:00:00');
        $this->createSnapshot($second, $run->id, '2026-08-21 11:00:01');

        Livewire::test(Show::class, ['site' => $site])
            ->assertDontSee('Зупинити перевірку')
            ->call('stopManualCheckRun')
            ->assertRedirect("/sites/{$site->id}")
            ->assertSessionHas('error', 'Немає активної ручної перевірки для зупинки.');

        $this->assertModelExists($run);
        $this->assertSame(2, $run->snapshots()->count());
    }

    public function test_another_user_cannot_stop_a_manual_check(): void
    {
        $owner = User::query()->firstOrFail();
        $site = Site::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Owned',
            'base_url' => 'https://owned.example.com',
        ]);
        $this->makeAddresses($site);
        $run = CheckRun::start($site, CheckRun::SOURCE_MANUAL, 2);

        $intruder = User::factory()->create();
        $this->actingAs($intruder);

        Livewire::test(Show::class, ['site' => $site])
            ->assertForbidden();

        $this->assertModelExists($run);
    }

    public function test_sites_index_can_stop_an_in_progress_manual_check(): void
    {
        $site = $this->makeSite();
        [$first] = $this->makeAddresses($site);
        $manual = CheckRun::start($site, CheckRun::SOURCE_MANUAL, 2);
        $this->createSnapshot($first, $manual->id, '2026-08-21 11:00:00');

        Livewire::test(Index::class)
            ->assertSee('Зупинити перевірку')
            ->call('stopManualCheckRun', $site->id)
            ->assertRedirect('/sites')
            ->assertSessionHas('success', 'Перевірку зупинено. Дані цього проходу видаляються.');

        $this->assertModelMissing($manual);
    }

    public function test_stopping_a_run_removes_its_pending_check_jobs(): void
    {
        config(['queue.default' => 'database']);

        $site = $this->makeSite();
        [$first, $second] = $this->makeAddresses($site);
        $manual = CheckRun::start($site, CheckRun::SOURCE_MANUAL, 2);
        CheckAddressJob::dispatch($first, $manual->id);
        CheckAddressJob::dispatch($second, $manual->id);

        Livewire::test(Show::class, ['site' => $site])
            ->assertSet('checksBusy', true)
            ->call('stopManualCheckRun')
            ->assertRedirect("/sites/{$site->id}");

        $this->assertSame(1, $site->checkRuns()->count());
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame(0, $manual->fresh()?->remaining_jobs);
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
    private function makeAddresses(Site $site): array
    {
        return [
            Address::query()->create([
                'site_id' => $site->id,
                'endpoint' => '/one',
            ]),
            Address::query()->create([
                'site_id' => $site->id,
                'endpoint' => '/two',
            ]),
        ];
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
