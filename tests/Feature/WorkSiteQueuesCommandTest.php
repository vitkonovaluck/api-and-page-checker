<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\WorkSiteQueuesCommand;
use App\Jobs\CheckAddressJob;
use App\Models\Address;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WorkSiteQueuesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_pretend_lists_a_queue_name_per_site(): void
    {
        $alpha = Site::query()->create([
            'name' => 'Alpha',
            'base_url' => 'https://alpha.example.com',
        ]);
        $beta = Site::query()->create([
            'name' => 'Beta',
            'base_url' => 'https://beta.example.com',
        ]);

        $this->artisan('sites:queue-work', ['--pretend' => true])
            ->expectsOutputToContain('#'.$alpha->id.' Alpha → site-'.$alpha->id)
            ->expectsOutputToContain('#'.$beta->id.' Beta → site-'.$beta->id)
            ->assertSuccessful();
    }

    public function test_child_worker_listens_to_that_site_queue(): void
    {
        $arguments = WorkSiteQueuesCommand::childArtisanArguments(12, listen: true);

        $this->assertSame('queue:listen', $arguments[0]);
        $this->assertSame('--queue=site-12', $arguments[1]);
    }

    public function test_child_worker_does_not_cap_attempts_so_rate_limited_jobs_can_retry(): void
    {
        $arguments = WorkSiteQueuesCommand::childArtisanArguments(12);

        $this->assertContains('--tries=0', $arguments);
    }

    public function test_check_job_is_dispatched_onto_the_site_queue(): void
    {
        Queue::fake();

        $site = Site::query()->create([
            'name' => 'Queued',
            'base_url' => 'https://api.example.com',
        ]);
        Address::query()->create([
            'site_id' => $site->id,
            'endpoint' => '/one',
        ]);

        $this->post("/sites/{$site->id}/check")
            ->assertRedirect("/sites/{$site->id}");

        Queue::assertPushedOn(Site::checkQueueName($site->id), CheckAddressJob::class);
    }

    public function test_settings_page_documents_per_site_workers(): void
    {
        $this->get(route('settings.index'))
            ->assertOk()
            ->assertSee('sites:queue-work');
    }

    public function test_docker_queue_service_runs_per_site_workers(): void
    {
        $compose = file_get_contents(base_path('docker-compose.yml'));

        $this->assertNotFalse($compose);
        $this->assertStringContainsString('sites:queue-work', $compose);
        $this->assertStringContainsString('--tries=0', $compose);
        $this->assertDoesNotMatchRegularExpression('/artisan", "queue:work"/', $compose);
    }
}
