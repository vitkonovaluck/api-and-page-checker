<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CheckAddressJob;
use App\Jobs\DeleteLatestManualCheckRunJob;
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

class DeleteLatestManualCheckRunTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsUser();
    }

    public function test_site_show_can_delete_the_last_manual_all_addresses_pass(): void
    {
        $site = $this->makeSite();
        [$first, $second] = $this->makeAddresses($site);

        $previous = CheckRun::start($site, CheckRun::SOURCE_SCHEDULE);
        $keptFirst = $this->createSnapshot($first, $previous->id, '2026-08-21 09:00:00');
        $keptSecond = $this->createSnapshot($second, $previous->id, '2026-08-21 09:00:01');

        $manual = CheckRun::start($site, CheckRun::SOURCE_MANUAL);
        $this->createSnapshot($first, $manual->id, '2026-08-21 11:00:00');
        $this->createSnapshot($second, $manual->id, '2026-08-21 11:00:01');

        Livewire::test(Show::class, ['site' => $site])
            ->assertSee('Видалити останній прохід')
            ->call('deleteLastManualCheckRun')
            ->assertRedirect("/sites/{$site->id}")
            ->assertSessionHas('success', 'Видалення останнього проходу запущено.');

        $this->assertModelMissing($manual);
        $this->assertModelExists($previous);
        $this->assertModelExists($keptFirst);
        $this->assertModelExists($keptSecond);
    }

    public function test_site_show_queues_deletion_instead_of_running_it_in_the_request(): void
    {
        Queue::fake();

        $site = $this->makeSite();
        [$first, $second] = $this->makeAddresses($site);

        $manual = CheckRun::start($site, CheckRun::SOURCE_MANUAL);
        $this->createSnapshot($first, $manual->id, '2026-08-21 11:00:00');
        $this->createSnapshot($second, $manual->id, '2026-08-21 11:00:01');

        Livewire::test(Show::class, ['site' => $site])
            ->call('deleteLastManualCheckRun')
            ->assertRedirect("/sites/{$site->id}")
            ->assertSessionHas('success', 'Видалення останнього проходу запущено.');

        Queue::assertPushedOn(Site::checkQueueName((int) $site->id), DeleteLatestManualCheckRunJob::class);
        $this->assertModelExists($manual);
        $this->assertSame(2, $manual->snapshots()->count());
    }

    public function test_delete_button_is_hidden_when_latest_run_is_scheduled(): void
    {
        $site = $this->makeSite();
        [$first, $second] = $this->makeAddresses($site);

        $manual = CheckRun::start($site, CheckRun::SOURCE_MANUAL);
        $this->createSnapshot($first, $manual->id, '2026-08-21 10:00:00');
        $this->createSnapshot($second, $manual->id, '2026-08-21 10:00:01');

        $schedule = CheckRun::start($site, CheckRun::SOURCE_SCHEDULE);
        $this->createSnapshot($first, $schedule->id, '2026-08-21 10:05:00');
        $this->createSnapshot($second, $schedule->id, '2026-08-21 10:05:01');

        Livewire::test(Show::class, ['site' => $site])
            ->assertDontSee('Видалити останній прохід');
    }

    public function test_in_progress_manual_run_cannot_be_deleted(): void
    {
        $site = $this->makeSite();
        [$first, $second] = $this->makeAddresses($site);

        $run = CheckRun::start($site, CheckRun::SOURCE_MANUAL, 2);
        $this->createSnapshot($first, $run->id, '2026-08-21 11:00:00');
        $this->createSnapshot($second, $run->id, '2026-08-21 11:00:01');

        Livewire::test(Show::class, ['site' => $site])
            ->assertDontSee('Видалити останній прохід')
            ->call('deleteLastManualCheckRun')
            ->assertRedirect("/sites/{$site->id}")
            ->assertSessionHas('error', 'Немає завершеного ручного проходу для видалення.');

        $this->assertModelExists($run);
        $this->assertSame(2, $run->snapshots()->count());
    }

    public function test_cannot_delete_last_pass_while_a_check_is_running(): void
    {
        config(['queue.default' => 'database']);

        $site = $this->makeSite();
        [$first, $second] = $this->makeAddresses($site);

        $run = CheckRun::start($site, CheckRun::SOURCE_MANUAL);
        $this->createSnapshot($first, $run->id, '2026-08-21 11:00:00');
        $this->createSnapshot($second, $run->id, '2026-08-21 11:00:01');

        CheckAddressJob::dispatch($first);

        Livewire::test(Show::class, ['site' => $site])
            ->assertSet('checksBusy', true)
            ->call('deleteLastManualCheckRun')
            ->assertRedirect("/sites/{$site->id}")
            ->assertSessionHas('error', 'Зараз уже виконується перевірка. Зачекайте завершення.');

        $this->assertModelExists($run);
        $this->assertSame(2, $run->snapshots()->count());
    }

    public function test_another_user_cannot_delete_the_last_manual_pass(): void
    {
        $owner = User::query()->firstOrFail();
        $site = Site::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Owned',
            'base_url' => 'https://owned.example.com',
        ]);
        [$first, $second] = $this->makeAddresses($site);

        $run = CheckRun::start($site, CheckRun::SOURCE_MANUAL);
        $this->createSnapshot($first, $run->id, '2026-08-21 11:00:00');
        $this->createSnapshot($second, $run->id, '2026-08-21 11:00:01');

        $intruder = User::factory()->create();

        $this->actingAs($intruder);

        Livewire::test(Show::class, ['site' => $site])
            ->assertForbidden();

        $this->assertModelExists($run);
        $this->assertSame(2, $run->snapshots()->count());
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
