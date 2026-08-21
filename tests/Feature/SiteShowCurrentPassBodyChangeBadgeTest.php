<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Sites\Show;
use App\Models\Address;
use App\Models\CheckRun;
use App\Models\Site;
use App\Models\Snapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteShowCurrentPassBodyChangeBadgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsUser();
    }

    public function test_address_table_header_shows_body_change_count_for_the_current_pass(): void
    {
        $site = $this->makeSite();
        [$first, $second, $unchanged] = $this->makeAddresses($site);

        $previous = CheckRun::start($site, CheckRun::SOURCE_MANUAL);
        $this->createSnapshot($first, $previous->id, '{"n":1}');
        $this->createSnapshot($second, $previous->id, '{"n":1}');
        $this->createSnapshot($unchanged, $previous->id, '{"ok":true}');

        $current = CheckRun::start($site, CheckRun::SOURCE_MANUAL);
        $this->createSnapshot($first, $current->id, '{"n":2}');
        $this->createSnapshot($second, $current->id, '{"n":3}');
        $this->createSnapshot($unchanged, $current->id, '{"ok":true}');

        Livewire::test(Show::class, ['site' => $site])
            ->assertSee('зміни: 2')
            ->assertSee('Зміни body в поточному проході перевірки');
    }

    public function test_body_change_badge_is_hidden_when_the_current_pass_has_no_changes(): void
    {
        $site = $this->makeSite();
        [$first, $second] = $this->makeAddresses($site, 2);

        $previous = CheckRun::start($site, CheckRun::SOURCE_MANUAL);
        $this->createSnapshot($first, $previous->id, '{"ok":true}');
        $this->createSnapshot($second, $previous->id, '{"ok":true}');

        $current = CheckRun::start($site, CheckRun::SOURCE_MANUAL);
        $this->createSnapshot($first, $current->id, '{"ok":true}');
        $this->createSnapshot($second, $current->id, '{"ok":true}');

        Livewire::test(Show::class, ['site' => $site])
            ->assertDontSee('зміни:')
            ->assertDontSee('Зміни body в поточному проході перевірки');
    }

    public function test_body_change_badge_ignores_changes_from_the_previous_pass(): void
    {
        $site = $this->makeSite();
        [$changedBefore, $checkedNow] = $this->makeAddresses($site, 2);

        $firstPass = CheckRun::start($site, CheckRun::SOURCE_MANUAL);
        $this->createSnapshot($changedBefore, $firstPass->id, '{"n":1}');
        $this->createSnapshot($checkedNow, $firstPass->id, '{"ok":true}');

        $secondPass = CheckRun::start($site, CheckRun::SOURCE_MANUAL);
        $this->createSnapshot($changedBefore, $secondPass->id, '{"n":2}');
        $this->createSnapshot($checkedNow, $secondPass->id, '{"ok":true}');

        $current = CheckRun::start($site, CheckRun::SOURCE_MANUAL);
        $this->createSnapshot($checkedNow, $current->id, '{"ok":false}');

        Livewire::test(Show::class, ['site' => $site])
            ->assertSee('зміни: 1');
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
    private function makeAddresses(Site $site, int $count = 3): array
    {
        $addresses = [];

        foreach (range(1, $count) as $index) {
            $addresses[] = Address::query()->create([
                'site_id' => $site->id,
                'endpoint' => '/endpoint-'.$index,
            ]);
        }

        return $addresses;
    }

    private function createSnapshot(Address $address, int $checkRunId, string $body): Snapshot
    {
        return Snapshot::query()->create([
            'address_id' => $address->id,
            'check_run_id' => $checkRunId,
            'status_code' => 200,
            'headers' => [],
            'body' => $body,
            'body_hash' => hash('sha256', $body),
            'response_time_ms' => 10,
        ]);
    }
}
