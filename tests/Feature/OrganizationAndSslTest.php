<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\InspectSslCertificateAction;
use App\Enums\AlertChannel;
use App\Enums\AlertEvent;
use App\Enums\OrganizationRole;
use App\Jobs\CheckSiteSslCertificatesJob;
use App\Livewire\Settings\AgentTokens;
use App\Livewire\Sites\Index as SitesIndex;
use App\Mail\SslExpiringMail;
use App\Models\AlertRule;
use App\Models\CheckAgent;
use App\Models\NotificationChannel;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationAndSslTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_member_can_see_shared_site(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization = $owner->personalOrganization();
        $this->assertNotNull($organization);
        $organization->users()->attach($member->id, [
            'role' => OrganizationRole::Viewer->value,
        ]);
        $site = Site::factory()->create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'name' => 'Shared API',
        ]);
        $site->refresh();

        $this->assertTrue($member->can('view', $site));

        $this->actingAs($member)
            ->get(route('sites.show', $site))
            ->assertOk()
            ->assertSee('Shared API');

        Livewire::actingAs($member)
            ->test(SitesIndex::class)
            ->assertSee('Shared API');
    }

    public function test_ssl_job_mails_when_certificate_expires_soon(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $site = Site::factory()->create([
            'user_id' => $user->id,
            'base_url' => 'https://api.example.com',
        ]);
        $channel = NotificationChannel::factory()->create([
            'user_id' => $user->id,
            'channel' => AlertChannel::Mail,
            'config' => ['email' => 'alerts@example.com'],
        ]);
        AlertRule::factory()->create([
            'site_id' => $site->id,
            'notification_channel_id' => $channel->id,
            'events' => [AlertEvent::SslExpiring->value],
        ]);
        $expires = now()->addDays(3)->timestamp;
        $this->mock(InspectSslCertificateAction::class, function ($mock) use ($expires): void {
            $mock->shouldReceive('hostFromSite')->andReturn('api.example.com');
            $mock->shouldReceive('execute')->andReturn($expires);
        });

        (new CheckSiteSslCertificatesJob)->handle(app(InspectSslCertificateAction::class));

        Mail::assertSent(SslExpiringMail::class);
        $this->assertNotNull($site->fresh()->ssl_expires_at);
    }

    public function test_agent_region_is_stored(): void
    {
        $this->actingAsUser();

        Livewire::test(AgentTokens::class)
            ->set('name', 'Office-PC')
            ->set('region', 'eu-west')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas((new CheckAgent)->getTable(), [
            'name' => 'Office-PC',
            'region' => 'eu-west',
        ]);
    }
}
