<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_view_another_users_site(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $site = Site::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)
            ->get(route('sites.show', $site))
            ->assertForbidden();
    }

    public function test_sites_index_only_lists_the_authenticated_users_sites(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Site::factory()->create([
            'user_id' => $user->id,
            'name' => 'Mine',
        ]);
        Site::factory()->create([
            'user_id' => $other->id,
            'name' => 'Theirs',
        ]);

        $this->actingAs($user)
            ->get(route('sites.index'))
            ->assertOk()
            ->assertSee('Mine')
            ->assertDontSee('Theirs');
    }
}
