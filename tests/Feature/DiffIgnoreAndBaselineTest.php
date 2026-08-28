<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\AcceptBaselineAction;
use App\DTOs\DiffOptionsDTO;
use App\Enums\ChangeIncidentStatus;
use App\Livewire\Addresses\Show as AddressShow;
use App\Models\Address;
use App\Models\ChangeIncident;
use App\Models\Site;
use App\Models\Snapshot;
use App\Services\DiffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DiffIgnoreAndBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_ignored_json_path_does_not_count_as_a_change(): void
    {
        $address = Address::factory()->create([
            'ignore_json_paths' => ['updated_at'],
        ]);
        $previous = Snapshot::factory()->create([
            'address_id' => $address->id,
            'body' => json_encode(['name' => 'ok', 'updated_at' => '2026-01-01'], JSON_THROW_ON_ERROR),
        ]);
        $current = Snapshot::factory()->create([
            'address_id' => $address->id,
            'body' => json_encode(['name' => 'ok', 'updated_at' => '2026-08-25'], JSON_THROW_ON_ERROR),
        ]);

        $diff = app(DiffService::class)->compare(
            $previous,
            $current,
            DiffOptionsDTO::fromAddress($address),
        );

        $this->assertFalse($diff['has_changes']);
    }

    public function test_ignored_header_is_case_insensitive(): void
    {
        $address = Address::factory()->create([
            'ignore_headers' => ['date'],
        ]);
        $previous = Snapshot::factory()->create([
            'address_id' => $address->id,
            'headers' => ['Date' => 'Mon', 'content-type' => 'application/json'],
            'body' => '{"ok":true}',
        ]);
        $current = Snapshot::factory()->create([
            'address_id' => $address->id,
            'headers' => ['date' => 'Tue', 'content-type' => 'application/json'],
            'body' => '{"ok":true}',
        ]);

        $diff = app(DiffService::class)->compare(
            $previous,
            $current,
            DiffOptionsDTO::fromAddress($address),
        );

        $this->assertFalse($diff['has_changes']);
        $this->assertSame([], $diff['headers']);
    }

    public function test_accept_baseline_closes_open_incidents(): void
    {
        $user = $this->actingAsUser();
        $address = Address::factory()->create();
        $snapshot = Snapshot::factory()->create(['address_id' => $address->id]);
        ChangeIncident::factory()->create([
            'address_id' => $address->id,
            'opened_snapshot_id' => $snapshot->id,
            'status' => ChangeIncidentStatus::Open,
        ]);

        app(AcceptBaselineAction::class)->execute($address, $snapshot, $user);

        $this->assertSame($snapshot->id, $address->fresh()->baseline_snapshot_id);
        $this->assertSame(
            ChangeIncidentStatus::Accepted,
            ChangeIncident::query()->where('address_id', $address->id)->first()?->status,
        );
    }

    public function test_address_show_can_accept_baseline(): void
    {
        $user = $this->actingAsUser();
        $site = Site::factory()->create(['user_id' => $user->id]);
        $address = Address::factory()->create(['site_id' => $site->id]);
        Snapshot::factory()->create([
            'address_id' => $address->id,
            'body' => json_encode(['v' => 1], JSON_THROW_ON_ERROR),
        ]);
        $latest = Snapshot::factory()->create([
            'address_id' => $address->id,
            'body' => json_encode(['v' => 2], JSON_THROW_ON_ERROR),
        ]);

        Livewire::test(AddressShow::class, ['site' => $site, 'address' => $address])
            ->call('acceptBaseline')
            ->assertHasNoErrors();

        $this->assertSame($latest->id, $address->fresh()->baseline_snapshot_id);
    }
}
