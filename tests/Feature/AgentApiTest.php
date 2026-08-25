<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CheckAgent;
use App\Models\CheckRun;
use App\Models\Plan;
use App\Models\Site;
use App\Models\SiteToken;
use App\Models\Snapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_issues_token_bound_to_existing_user(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/agent/login', $this->loginPayload($user));

        $response->assertCreated()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('agent.name', 'Office-PC')
            ->assertJsonPath('token_type', 'Bearer');
        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseHas((new CheckAgent)->getTable(), [
            'user_id' => $user->id,
            'name' => 'Office-PC',
        ]);
        $this->assertSame(1, User::query()->count());
    }

    public function test_login_rejects_unknown_email_without_creating_a_user(): void
    {
        $response = $this->postJson('/api/v1/agent/login', [
            'email' => 'missing@example.com',
            'password' => 'password',
            'name' => 'Office-PC',
        ]);

        $response->assertUnauthorized();
        $this->assertSame(0, User::query()->count());
        $this->assertDatabaseCount((new CheckAgent)->getTable(), 0);
    }

    public function test_login_rejects_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/agent/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'name' => 'Office-PC',
        ])->assertUnauthorized();

        $this->assertSame(1, User::query()->count());
        $this->assertDatabaseCount((new CheckAgent)->getTable(), 0);
    }

    public function test_login_rejects_social_only_user(): void
    {
        $user = User::factory()->socialOnly()->create();

        $this->postJson('/api/v1/agent/login', $this->loginPayload($user))
            ->assertForbidden();

        $this->assertDatabaseCount((new CheckAgent)->getTable(), 0);
    }

    public function test_me_returns_the_authenticated_user_and_agent(): void
    {
        $user = User::factory()->create();
        $token = $this->agentToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/agent/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('agent.name', 'Office-PC');

        $this->assertNotNull(
            CheckAgent::query()->where('user_id', $user->id)->value('last_seen_at'),
        );
    }

    public function test_sites_catalog_excludes_other_users_sites(): void
    {
        $user = User::factory()->create();
        $own = Site::factory()->create([
            'user_id' => $user->id,
            'name' => 'Own Site',
            'base_url' => 'https://own.example.com',
        ]);
        Address::query()->create([
            'site_id' => $own->id,
            'name' => 'Health',
            'endpoint' => '/health',
        ]);
        $other = Site::factory()->create([
            'user_id' => User::factory(),
            'name' => 'Secret Site',
            'base_url' => 'https://other.example.com',
        ]);
        Address::query()->create([
            'site_id' => $other->id,
            'endpoint' => '/secret',
        ]);

        $this->withToken($this->agentToken($user))
            ->getJson('/api/v1/agent/sites')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Own Site')
            ->assertJsonPath('data.0.addresses.0.full_url', 'https://own.example.com/health')
            ->assertJsonMissing(['name' => 'Secret Site']);
    }

    public function test_sites_catalog_includes_authorization_from_connected_token(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->create([
            'user_id' => $user->id,
            'name' => 'Own Site',
            'base_url' => 'https://own.example.com',
        ]);
        $token = SiteToken::factory()->create([
            'site_id' => $site->id,
            'name' => 'Prod',
            'value' => 'agent-secret',
        ]);
        Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/health',
            'site_token_id' => $token->id,
            'request_headers' => ['X-Custom' => 'yes'],
        ]);

        $this->withToken($this->agentToken($user))
            ->getJson('/api/v1/agent/sites')
            ->assertOk()
            ->assertJsonPath('data.0.addresses.0.request_headers.'.SiteToken::HEADER_NAME, 'Bearer agent-secret')
            ->assertJsonPath('data.0.addresses.0.request_headers.X-Custom', 'yes');
    }

    public function test_agent_can_start_a_check_run_and_store_a_snapshot(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->create([
            'user_id' => $user->id,
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/health',
        ]);
        $token = $this->agentToken($user);

        $runResponse = $this->withToken($token)
            ->postJson('/api/v1/agent/check-runs', [
                'site_id' => $site->id,
                'address_ids' => [$address->id],
            ]);

        $runResponse->assertCreated()
            ->assertJsonPath('source', CheckRun::SOURCE_AGENT)
            ->assertJsonPath('remaining_jobs', 1);
        $runId = $runResponse->json('id');
        $body = '{"ok":true}';

        $this->withToken($token)
            ->postJson('/api/v1/agent/check-runs/'.$runId.'/snapshots', [
                'address_id' => $address->id,
                'status_code' => 200,
                'headers' => ['content-type' => 'application/json'],
                'body' => $body,
                'response_time_ms' => 42,
            ])
            ->assertCreated()
            ->assertJsonPath('address_id', $address->id)
            ->assertJsonPath('body_hash', hash('sha256', $body));

        $snapshot = Snapshot::query()->first();
        $this->assertNotNull($snapshot);
        $this->assertSame(CheckRun::SOURCE_AGENT, $snapshot->checkRun?->source);
        $this->assertSame(hash('sha256', $body), $snapshot->body_hash);
        $this->assertNotNull($address->fresh()?->last_checked_at);
        $this->assertSame(0, CheckRun::query()->find($runId)?->remaining_jobs);
    }

    public function test_snapshot_for_foreign_address_is_rejected(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->create([
            'user_id' => $user->id,
            'base_url' => 'https://api.example.com',
        ]);
        $ownAddress = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/health',
        ]);
        $foreign = Address::query()->create([
            'site_id' => Site::factory()->create(['base_url' => 'https://other.example.com'])->id,
            'endpoint' => '/secret',
        ]);
        $token = $this->agentToken($user);
        $runId = $this->withToken($token)
            ->postJson('/api/v1/agent/check-runs', [
                'site_id' => $site->id,
                'address_ids' => [$ownAddress->id],
            ])
            ->json('id');

        $this->withToken($token)
            ->postJson('/api/v1/agent/check-runs/'.$runId.'/snapshots', [
                'address_id' => $foreign->id,
                'status_code' => 200,
                'body' => 'ok',
                'response_time_ms' => 10,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount((new Snapshot)->getTable(), 0);
    }

    public function test_duplicate_snapshot_in_the_same_run_is_conflict(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->create([
            'user_id' => $user->id,
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/health',
        ]);
        $token = $this->agentToken($user);
        $runId = $this->withToken($token)
            ->postJson('/api/v1/agent/check-runs', [
                'site_id' => $site->id,
                'address_ids' => [$address->id],
            ])
            ->json('id');
        $payload = [
            'address_id' => $address->id,
            'status_code' => 200,
            'body' => 'ok',
            'response_time_ms' => 10,
        ];

        $this->withToken($token)
            ->postJson('/api/v1/agent/check-runs/'.$runId.'/snapshots', $payload)
            ->assertCreated();
        $this->withToken($token)
            ->postJson('/api/v1/agent/check-runs/'.$runId.'/snapshots', $payload)
            ->assertConflict();

        $this->assertDatabaseCount((new Snapshot)->getTable(), 1);
    }

    public function test_invalid_bearer_token_is_rejected(): void
    {
        $this->withToken('not-a-real-token')
            ->getJson('/api/v1/agent/me')
            ->assertUnauthorized();
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $this->agentToken($user);

        $this->withToken($token)
            ->postJson('/api/v1/agent/logout')
            ->assertOk();

        $this->assertSame(0, $user->fresh()?->tokens()->count());

        auth()->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/agent/me')
            ->assertUnauthorized();
    }

    public function test_agent_can_import_endpoints_and_skip_existing(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->create([
            'user_id' => $user->id,
            'base_url' => 'https://shop.example.com',
        ]);
        Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/catalog',
        ]);

        $this->withToken($this->agentToken($user))
            ->postJson('/api/v1/agent/sites/'.$site->id.'/addresses', [
                'endpoints' => ['/catalog', '/product/12', '/product/12', 'https://shop.example.com/about?ref=nav'],
                'schedule_enabled' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('created', 2)
            ->assertJsonPath('skipped', 2)
            ->assertJsonPath('addresses.0.http_method', 'GET')
            ->assertJsonPath('addresses.0.schedule_enabled', true);

        $this->assertEqualsCanonicalizing(
            ['/about?ref=nav', '/catalog', '/product/12'],
            $site->addresses()->pluck('endpoint')->all(),
        );
    }

    public function test_agent_import_strips_site_base_path_from_absolute_urls(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->create([
            'user_id' => $user->id,
            'base_url' => 'https://shop.example.com/app',
        ]);

        $this->withToken($this->agentToken($user))
            ->postJson('/api/v1/agent/sites/'.$site->id.'/addresses', [
                'endpoints' => [
                    'https://shop.example.com/app/users',
                    'https://other.example.com/secret',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('skipped', 1)
            ->assertJsonPath('addresses.0.endpoint', '/users')
            ->assertJsonPath('addresses.0.full_url', 'https://shop.example.com/app/users');
    }

    public function test_agent_import_all_duplicates_returns_ok_without_creating(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->create([
            'user_id' => $user->id,
            'base_url' => 'https://shop.example.com',
        ]);
        Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/health',
        ]);

        $this->withToken($this->agentToken($user))
            ->postJson('/api/v1/agent/sites/'.$site->id.'/addresses', [
                'endpoints' => ['/health'],
            ])
            ->assertOk()
            ->assertJsonPath('created', 0)
            ->assertJsonPath('skipped', 1)
            ->assertJsonPath('addresses', []);

        $this->assertSame(1, $site->addresses()->count());
    }

    public function test_agent_cannot_import_addresses_for_another_users_site(): void
    {
        $user = User::factory()->create();
        $foreign = Site::factory()->create([
            'user_id' => User::factory(),
            'base_url' => 'https://other.example.com',
        ]);

        $this->withToken($this->agentToken($user))
            ->postJson('/api/v1/agent/sites/'.$foreign->id.'/addresses', [
                'endpoints' => ['/secret'],
            ])
            ->assertForbidden();

        $this->assertSame(0, $foreign->addresses()->count());
    }

    public function test_agent_import_respects_plan_address_quota(): void
    {
        $plan = Plan::factory()->create([
            'max_sites' => 3,
            'max_addresses_per_site' => 2,
        ]);
        $user = User::factory()->create(['plan_id' => $plan->id]);
        $site = Site::factory()->create(['user_id' => $user->id]);
        $site->addresses()->create(['endpoint' => '/one']);
        $site->addresses()->create(['endpoint' => '/two']);

        $this->withToken($this->agentToken($user))
            ->postJson('/api/v1/agent/sites/'.$site->id.'/addresses', [
                'endpoints' => ['/three'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['endpoints']);

        $this->assertSame(2, $site->addresses()->count());
    }

    /**
     * @return array{email: string, password: string, name: string, hostname: string}
     */
    private function loginPayload(User $user): array
    {
        return [
            'email' => $user->email,
            'password' => 'password',
            'name' => 'Office-PC',
            'hostname' => 'DESKTOP-ABC',
        ];
    }

    private function agentToken(User $user): string
    {
        $token = $this->postJson('/api/v1/agent/login', $this->loginPayload($user))->json('token');
        $this->assertIsString($token);

        return $token;
    }
}
