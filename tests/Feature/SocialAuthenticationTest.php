<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SocialProvider;
use App\Models\Plan;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class SocialAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.google.client_id', 'test-google-id');
        config()->set('services.google.client_secret', 'test-google-secret');
    }

    public function test_unknown_provider_returns_not_found(): void
    {
        $this->get(route('auth.social.redirect', 'unknown'))
            ->assertNotFound();
    }

    public function test_oauth_creates_a_user_on_the_default_plan(): void
    {
        $plan = Plan::factory()->default()->create();
        $this->fakeSocialiteUser('99', 'oauth@example.com', 'OAuth User');

        $this->get(route('auth.social.callback', 'google'))
            ->assertRedirect(route('sites.index'));

        $user = User::query()->where('email', 'oauth@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->plan->is($plan));
        $this->assertNull($user->password);
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => SocialProvider::Google->value,
            'provider_id' => '99',
        ]);
    }

    public function test_oauth_links_an_existing_email_without_creating_a_second_user(): void
    {
        Plan::factory()->default()->create();
        $user = User::factory()->create(['email' => 'same@example.com']);
        $this->fakeSocialiteUser('77', 'same@example.com', 'Same Person');

        $this->get(route('auth.social.callback', 'google'))
            ->assertRedirect(route('sites.index'));

        $this->assertSame(1, User::query()->where('email', 'same@example.com')->count());
        $this->assertTrue(
            SocialAccount::query()
                ->where('user_id', $user->id)
                ->where('provider', SocialProvider::Google)
                ->where('provider_id', '77')
                ->exists(),
        );
        $this->assertAuthenticatedAs($user);
    }

    public function test_oauth_logs_in_an_existing_social_account(): void
    {
        Plan::factory()->default()->create();
        $user = User::factory()->create();
        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => SocialProvider::Google,
            'provider_id' => '55',
        ]);
        $this->fakeSocialiteUser('55', 'ignored@example.com', 'Ignored');

        $this->get(route('auth.social.callback', 'google'))
            ->assertRedirect(route('sites.index'));

        $this->assertSame(1, User::query()->count());
        $this->assertAuthenticatedAs($user);
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
}
