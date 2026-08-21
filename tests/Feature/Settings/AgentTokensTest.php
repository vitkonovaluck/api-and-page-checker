<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Livewire\Settings\AgentTokens;
use App\Models\CheckAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgentTokensTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_includes_agent_token_manager(): void
    {
        $this->actingAsUser();

        $this->get(route('settings.index'))
            ->assertOk()
            ->assertSeeLivewire(AgentTokens::class);
    }

    public function test_user_can_create_an_agent_token_from_settings(): void
    {
        $user = $this->actingAsUser();

        $component = Livewire::test(AgentTokens::class)
            ->set('name', 'Office-PC')
            ->set('hostname', 'DESKTOP-ABC')
            ->call('create');

        $component->assertHasNoErrors();
        $this->assertNotEmpty($component->get('plainTextToken'));
        $this->assertDatabaseHas((new CheckAgent)->getTable(), [
            'user_id' => $user->id,
            'name' => 'Office-PC',
            'hostname' => 'DESKTOP-ABC',
        ]);
    }

    public function test_user_can_revoke_own_agent(): void
    {
        $user = $this->actingAsUser();
        Livewire::test(AgentTokens::class)
            ->set('name', 'Office-PC')
            ->call('create');
        $agent = CheckAgent::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($agent);

        Livewire::test(AgentTokens::class)
            ->call('revoke', $agent)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing((new CheckAgent)->getTable(), [
            'id' => $agent->id,
        ]);
    }

    public function test_user_cannot_revoke_another_users_agent(): void
    {
        $this->actingAsUser();
        $foreign = CheckAgent::factory()->create([
            'user_id' => User::factory(),
            'name' => 'Foreign-PC',
        ]);

        Livewire::test(AgentTokens::class)
            ->call('revoke', $foreign)
            ->assertForbidden();

        $this->assertDatabaseHas((new CheckAgent)->getTable(), [
            'id' => $foreign->id,
        ]);
    }
}
