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

class DiffReorderDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_address_page_labels_slug_list_reorder(): void
    {
        $site = Site::query()->create([
            'name' => 'STO',
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'name' => 'Locations',
            'endpoint' => '/address',
        ]);

        $cetus = ['name' => 'СТО Cetus', 'slug' => 'cetus'];
        $avtoMotiv = ['name' => 'СТО Авто-Мотив', 'slug' => 'avto-motiv'];
        $oldBody = json_encode(['address' => [$cetus, $avtoMotiv]], JSON_UNESCAPED_UNICODE);
        $newBody = json_encode(['address' => [$avtoMotiv, $cetus]], JSON_UNESCAPED_UNICODE);

        Snapshot::query()->create([
            'address_id' => $address->id,
            'status_code' => 200,
            'headers' => [],
            'body' => $oldBody,
            'body_hash' => hash('sha256', $oldBody),
            'response_time_ms' => 10,
        ]);
        Snapshot::query()->create([
            'address_id' => $address->id,
            'status_code' => 200,
            'headers' => [],
            'body' => $newBody,
            'body_hash' => hash('sha256', $newBody),
            'response_time_ms' => 12,
        ]);

        Livewire::test(AddressShow::class, ['site' => $site, 'address' => $address])
            ->assertSee('пересортовано')
            ->assertSee('address')
            ->assertDontSee('address[0].name');
    }
}
