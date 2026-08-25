<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\ExtensionAgentLoginAction;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class ExtensionAgentLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.google.client_id', 'test-google-id');
        config()->set('services.google.client_secret', 'test-google-secret');
    }

    public function test_providers_catalog_lists_configured_social_networks(): void
    {
        $this->getJson('/api/v1/agent/providers')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'google')
            ->assertJsonPath('data.0.label', 'Google');
    }

    public function test_extension_login_ticket_stays_pending_until_social_completes(): void
    {
        $ticket = $this->postJson('/api/v1/agent/extension-logins', [
            'name' => 'Chrome',
            'hostname' => 'chrome-extension',
        ])
            ->assertCreated()
            ->json('ticket');

        $this->assertIsString($ticket);

        $this->getJson('/api/v1/agent/extension-logins/'.$ticket)
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('token', null);
    }

    public function test_signed_in_user_can_connect_the_extension_without_oauth(): void
    {
        $user = User::factory()->create();
        $ticket = app(ExtensionAgentLoginAction::class)->start('Chrome', 'chrome-extension');

        $this->actingAs($user)
            ->get(route('extension.auth', ['provider' => 'google', 'ticket' => $ticket]))
            ->assertRedirect(route('extension.connected'));

        $token = $this->getJson('/api/v1/agent/extension-logins/'.$ticket)
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->json('token');

        $this->assertIsString($token);
        $this->assertNotSame('', $token);

        $this->getJson('/api/v1/agent/extension-logins/'.$ticket)
            ->assertNotFound();

        $this->withToken($token)
            ->getJson('/api/v1/agent/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('agent.name', 'Chrome');
    }

    public function test_oauth_callback_with_extension_ticket_issues_an_agent_token(): void
    {
        Plan::factory()->default()->create();
        $ticket = app(ExtensionAgentLoginAction::class)->start('Chrome', 'chrome-extension');
        $this->fakeSocialiteUser('99', 'oauth@example.com', 'OAuth User');

        $this->withSession([ExtensionAgentLoginAction::SESSION_KEY => $ticket])
            ->get(route('auth.social.callback', 'google'))
            ->assertRedirect(route('extension.connected'));

        $token = $this->getJson('/api/v1/agent/extension-logins/'.$ticket)
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->json('token');

        $this->assertIsString($token);

        $this->withToken($token)
            ->getJson('/api/v1/agent/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'oauth@example.com');
    }

    public function test_guest_extension_auth_starts_the_site_oauth_redirect(): void
    {
        $ticket = app(ExtensionAgentLoginAction::class)->start('Chrome', null);
        $this->fakeSocialiteRedirect();

        $this->get(route('extension.auth', ['provider' => 'google', 'ticket' => $ticket]))
            ->assertRedirect('https://accounts.google.test/oauth');
    }

    public function test_unknown_extension_ticket_is_not_found(): void
    {
        $this->get(route('extension.auth', [
            'provider' => 'google',
            'ticket' => str_repeat('ab', 32),
        ]))->assertNotFound();

        $this->getJson('/api/v1/agent/extension-logins/'.str_repeat('cd', 32))
            ->assertNotFound();
    }

    public function test_oauth_failure_with_extension_ticket_marks_the_login_failed(): void
    {
        $ticket = app(ExtensionAgentLoginAction::class)->start('Chrome', 'chrome-extension');

        Socialite::shouldReceive('driver')->with('google')->andThrow(new \RuntimeException('denied'));

        $this->withSession([ExtensionAgentLoginAction::SESSION_KEY => $ticket])
            ->get(route('auth.social.callback', 'google'))
            ->assertRedirect(route('extension.connected'));

        $this->getJson('/api/v1/agent/extension-logins/'.$ticket)
            ->assertOk()
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('token', null);
    }

    private function fakeSocialiteUser(string $id, string $email, string $name): void
    {
        $oauthUser = Mockery::mock(SocialiteUser::class);
        $oauthUser->shouldReceive('getId')->andReturn($id);
        $oauthUser->shouldReceive('getEmail')->andReturn($email);
        $oauthUser->shouldReceive('getName')->andReturn($name);

        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andReturn($oauthUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function fakeSocialiteRedirect(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.test/oauth'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
