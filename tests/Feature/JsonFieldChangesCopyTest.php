<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Addresses\Show as AddressShow;
use App\Models\Address;
use App\Models\Site;
use App\Models\Snapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class JsonFieldChangesCopyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsUser();
    }

    public function test_address_show_renders_copy_button_for_json_field_changes(): void
    {
        [$site, $address] = $this->createAddressWithSnapshots(
            json_encode(['version' => 1, 'name' => 'old']),
            json_encode(['version' => 2, 'name' => 'new']),
        );

        Livewire::test(AddressShow::class, ['site' => $site, 'address' => $address])
            ->assertOk()
            ->assertSee('Поля JSON, що змінилися')
            ->assertSee('Копіювати всі зміни')
            ->assertSeeHtml('copyAll');
    }

    public function test_address_show_embeds_json_changes_payload_for_clipboard(): void
    {
        [$site, $address] = $this->createAddressWithSnapshots(
            json_encode(['version' => 1, 'name' => 'old']),
            json_encode(['version' => 2, 'name' => 'new']),
        );

        Livewire::test(AddressShow::class, ['site' => $site, 'address' => $address])
            ->assertOk()
            ->assertSeeHtml('\u0022path\u0022: \u0022version\u0022')
            ->assertSeeHtml('\u0022path\u0022: \u0022name\u0022');
    }

    public function test_address_show_hides_copy_button_when_json_body_is_unchanged(): void
    {
        $body = json_encode(['version' => 1, 'name' => 'same']);
        [$site, $address] = $this->createAddressWithSnapshots($body, $body);

        Livewire::test(AddressShow::class, ['site' => $site, 'address' => $address])
            ->assertOk()
            ->assertDontSee('Поля JSON, що змінилися')
            ->assertDontSee('Копіювати всі зміни');
    }

    /**
     * @return array{0: Site, 1: Address}
     */
    private function createAddressWithSnapshots(string $oldBody, string $newBody): array
    {
        $site = Site::factory()->create([
            'name' => 'Demo',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'name' => 'Data',
            'endpoint' => '/data',
        ]);

        $this->createSnapshot($address, $oldBody, 100);
        $this->createSnapshot($address, $newBody, 110);

        return [$site, $address];
    }

    private function createSnapshot(Address $address, string $body, int $responseTimeMs): void
    {
        Snapshot::query()->create([
            'address_id' => $address->id,
            'status_code' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => $body,
            'body_hash' => hash('sha256', $body),
            'response_time_ms' => $responseTimeMs,
        ]);
    }
}
