<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Site;
use App\Models\Snapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentSiteBodyChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_json_body_changes_are_returned_for_the_requested_site(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->create([
            'user_id' => $user->id,
            'name' => 'Catalog',
            'base_url' => 'https://api.example.com',
        ]);
        $address = $this->createAddress($site, '/products', 'Products');
        $this->createSnapshot($address, json_encode(['version' => 1, 'name' => 'old']));
        $this->createSnapshot($address, json_encode(['version' => 2, 'name' => 'new']));

        $response = $this->withToken($this->agentToken($user))
            ->getJson('/api/v1/agent/sites/'.$site->id.'/body-changes');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $address->id)
            ->assertJsonPath('data.0.full_url', 'https://api.example.com/products')
            ->assertJsonPath('data.0.body.type', 'json')
            ->assertJsonPath('data.0.body.changes.0.path', 'name')
            ->assertJsonPath('data.0.body.changes.0.old', 'old')
            ->assertJsonPath('data.0.body.changes.0.new', 'new')
            ->assertJsonPath('data.0.body.changes.1.path', 'version')
            ->assertJsonPath('data.0.body.changes.1.old', 1)
            ->assertJsonPath('data.0.body.changes.1.new', 2);
    }

    public function test_unchanged_and_first_snapshot_addresses_are_omitted(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->create([
            'user_id' => $user->id,
            'base_url' => 'https://api.example.com',
        ]);
        $unchanged = $this->createAddress($site, '/health', 'Health');
        $sameBody = json_encode(['ok' => true]);
        $this->createSnapshot($unchanged, $sameBody);
        $this->createSnapshot($unchanged, $sameBody);

        $firstOnly = $this->createAddress($site, '/ready', 'Ready');
        $this->createSnapshot($firstOnly, json_encode(['ok' => true]));

        $changed = $this->createAddress($site, '/version', 'Version');
        $this->createSnapshot($changed, json_encode(['n' => 1]));
        $this->createSnapshot($changed, json_encode(['n' => 2]));

        $response = $this->withToken($this->agentToken($user))
            ->getJson('/api/v1/agent/sites/'.$site->id.'/body-changes');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $changed->id);
    }

    public function test_text_body_changes_include_a_line_diff(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->create([
            'user_id' => $user->id,
            'base_url' => 'https://pages.example.com',
        ]);
        $address = $this->createAddress($site, '/about', 'About');
        $this->createSnapshot($address, "hello\nworld");
        $this->createSnapshot($address, "hello\neveryone");

        $response = $this->withToken($this->agentToken($user))
            ->getJson('/api/v1/agent/sites/'.$site->id.'/body-changes');

        $response->assertOk()
            ->assertJsonPath('data.0.body.type', 'text')
            ->assertJsonPath('data.0.body.changes', [])
            ->assertJsonFragment(['- world'])
            ->assertJsonFragment(['+ everyone']);
    }

    public function test_foreign_site_is_forbidden(): void
    {
        $user = User::factory()->create();
        $foreign = Site::factory()->create([
            'user_id' => User::factory(),
            'base_url' => 'https://other.example.com',
        ]);
        $address = $this->createAddress($foreign, '/secret', 'Secret');
        $this->createSnapshot($address, json_encode(['n' => 1]));
        $this->createSnapshot($address, json_encode(['n' => 2]));

        $this->withToken($this->agentToken($user))
            ->getJson('/api/v1/agent/sites/'.$foreign->id.'/body-changes')
            ->assertForbidden();
    }

    public function test_guest_is_unauthorized(): void
    {
        $site = Site::factory()->create([
            'base_url' => 'https://api.example.com',
        ]);

        $this->getJson('/api/v1/agent/sites/'.$site->id.'/body-changes')
            ->assertUnauthorized();
    }

    private function createAddress(Site $site, string $endpoint, string $name): Address
    {
        return Address::query()->create([
            'site_id' => $site->id,
            'name' => $name,
            'endpoint' => $endpoint,
        ]);
    }

    private function createSnapshot(Address $address, string $body): Snapshot
    {
        return Snapshot::query()->create([
            'address_id' => $address->id,
            'status_code' => 200,
            'headers' => ['content-type' => 'application/json'],
            'body' => $body,
            'body_hash' => hash('sha256', $body),
            'response_time_ms' => 40,
        ]);
    }

    private function agentToken(User $user): string
    {
        $token = $this->postJson('/api/v1/agent/login', [
            'email' => $user->email,
            'password' => 'password',
            'name' => 'Office-PC',
            'hostname' => 'DESKTOP-ABC',
        ])->json('token');
        $this->assertIsString($token);

        return $token;
    }
}
