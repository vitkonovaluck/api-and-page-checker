<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTOs\DiffOptionsDTO;
use App\Enums\AddressKind;
use App\Enums\DiffClassification;
use App\Models\Address;
use App\Models\Site;
use App\Models\Snapshot;
use App\Models\User;
use App\Services\DiffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OpenApiAndPublicApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_openapi_kind_diffs_paths_semantically(): void
    {
        $address = Address::factory()->create([
            'kind' => AddressKind::OpenApi,
        ]);
        $previous = Snapshot::factory()->create([
            'address_id' => $address->id,
            'body' => json_encode($this->openApiSpec(['200']), JSON_THROW_ON_ERROR),
        ]);
        $current = Snapshot::factory()->create([
            'address_id' => $address->id,
            'body' => json_encode($this->openApiSpec(['200', '401']), JSON_THROW_ON_ERROR),
        ]);

        $diff = app(DiffService::class)->compare(
            $previous,
            $current,
            DiffOptionsDTO::fromAddress($address),
        );

        $this->assertTrue($diff['has_changes']);
        $this->assertSame(DiffClassification::SchemaChange->value, $diff['classification']);
        $paths = array_column($diff['body']['changes'], 'path');
        $this->assertNotEmpty($paths);
    }

    public function test_api_token_can_crud_sites_and_start_a_check_run(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $token = $user->createToken('ci', ['api'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/sites', [
                'name' => 'CI Site',
                'base_url' => 'https://api.example.com',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'CI Site');

        $site = Site::query()->where('name', 'CI Site')->first();
        $this->assertNotNull($site);

        $this->withToken($token)
            ->postJson('/api/v1/sites/'.$site->id.'/addresses', [
                'endpoint' => '/health',
            ])
            ->assertCreated()
            ->assertJsonPath('data.endpoint', '/health');

        $address = $site->addresses()->first();
        $this->assertNotNull($address);

        $this->withToken($token)
            ->postJson('/api/v1/sites/'.$site->id.'/check-runs')
            ->assertCreated()
            ->assertJsonPath('data.site_id', $site->id);

        $from = Snapshot::factory()->create([
            'address_id' => $address->id,
            'body' => json_encode(['v' => 1], JSON_THROW_ON_ERROR),
        ]);
        $to = Snapshot::factory()->create([
            'address_id' => $address->id,
            'body' => json_encode(['v' => 2], JSON_THROW_ON_ERROR),
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/sites/'.$site->id.'/addresses/'.$address->id.'/diff?from='.$from->id.'&to='.$to->id)
            ->assertOk()
            ->assertJsonPath('diff.has_changes', true);

        $this->withToken($token)
            ->postJson('/api/v1/sites/'.$site->id.'/addresses/'.$address->id.'/baseline', [
                'snapshot_id' => $to->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.baseline_snapshot_id', $to->id);
    }

    public function test_agent_token_cannot_use_public_api(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('agent:1', ['agent'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/sites')
            ->assertForbidden();
    }

    /**
     * @param  list<string>  $responseCodes
     * @return array<string, mixed>
     */
    private function openApiSpec(array $responseCodes): array
    {
        $responses = [];

        foreach ($responseCodes as $code) {
            $responses[$code] = ['description' => 'ok'];
        }

        return [
            'openapi' => '3.0.0',
            'paths' => [
                '/pets' => [
                    'get' => [
                        'responses' => $responses,
                    ],
                ],
            ],
        ];
    }
}
