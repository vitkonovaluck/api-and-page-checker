<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CheckAddressJob;
use App\Livewire\Addresses\Show as AddressShow;
use App\Models\Address;
use App\Models\CheckRun;
use App\Models\Site;
use App\Models\Snapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class SnapshotCompareAndMultiStepTest extends TestCase
{
    use RefreshDatabase;

    public function test_address_show_can_compare_arbitrary_snapshots(): void
    {
        $user = $this->actingAsUser();
        $site = Site::factory()->create(['user_id' => $user->id]);
        $address = Address::factory()->create(['site_id' => $site->id]);
        $monday = Snapshot::factory()->create([
            'address_id' => $address->id,
            'body' => json_encode(['day' => 'mon'], JSON_THROW_ON_ERROR),
        ]);
        Snapshot::factory()->create([
            'address_id' => $address->id,
            'body' => json_encode(['day' => 'wed'], JSON_THROW_ON_ERROR),
        ]);
        Snapshot::factory()->create([
            'address_id' => $address->id,
            'body' => json_encode(['day' => 'fri'], JSON_THROW_ON_ERROR),
        ]);

        Livewire::test(AddressShow::class, ['site' => $site, 'address' => $address])
            ->set('compareFromId', $monday->id)
            ->assertSee('mon')
            ->assertSee('fri');
    }

    public function test_multi_step_extracts_token_for_the_next_request(): void
    {
        $user = $this->actingAsUser();
        $site = Site::factory()->create([
            'user_id' => $user->id,
            'base_url' => 'https://api.example.com',
        ]);
        $login = Address::factory()->create([
            'site_id' => $site->id,
            'endpoint' => '/login',
            'http_method' => 'POST',
            'request_body' => '{"user":"a"}',
            'step_order' => 1,
            'extract_json_path' => '$.token',
            'extract_as' => 'token',
        ]);
        Address::factory()->create([
            'site_id' => $site->id,
            'endpoint' => '/me',
            'http_method' => 'GET',
            'request_headers' => ['Authorization' => 'Bearer {{token}}'],
            'step_order' => 2,
        ]);
        Http::fake([
            'https://api.example.com/login' => Http::response(['token' => 'abc'], 200),
            'https://api.example.com/me' => Http::response(['id' => 7], 200),
        ]);

        CheckAddressJob::dispatchForSite(
            $site,
            CheckRun::SOURCE_MANUAL,
            $site->addresses()->orderBy('step_order')->get(),
        );

        $meSnapshot = Snapshot::query()
            ->whereHas('address', fn ($query) => $query->where('endpoint', '/me'))
            ->first();

        $this->assertNotNull($meSnapshot);
        $this->assertSame('{"id":7}', $meSnapshot->body);
        Http::assertSent(function ($request): bool {
            return str_ends_with($request->url(), '/me')
                && $request->hasHeader('Authorization', 'Bearer abc');
        });
        $this->assertSame('abc', CheckRun::query()->latest('id')->first()?->variables['token'] ?? null);
        $this->assertSame(1, $login->snapshots()->count());
    }
}
