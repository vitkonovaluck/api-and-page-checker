<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\EvaluateAssertionsAction;
use App\Enums\AssertionOperator;
use App\Enums\AssertionType;
use App\Enums\CheckOutcome;
use App\Models\Address;
use App\Models\CheckRun;
use App\Models\Site;
use App\Services\FetchResult;
use App\Services\SnapshotChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AddressAssertionTest extends TestCase
{
    use RefreshDatabase;

    public function test_json_path_error_status_fails_even_when_http_is_200(): void
    {
        $address = Address::factory()->create([
            'assertions' => [[
                'type' => AssertionType::JsonPath->value,
                'path' => '$.status',
                'op' => AssertionOperator::Neq->value,
                'value' => 'error',
            ]],
        ]);
        $result = new FetchResult(
            statusCode: 200,
            headers: ['content-type' => 'application/json'],
            body: json_encode(['status' => 'error'], JSON_THROW_ON_ERROR),
            responseTimeMs: 40,
        );

        $evaluated = app(EvaluateAssertionsAction::class)->execute($address, $result);

        $this->assertTrue($evaluated['failed']);
        $this->assertFalse($evaluated['results'][0]['passed']);
    }

    public function test_slow_ttfb_marks_snapshot_degraded(): void
    {
        $user = $this->actingAsUser();
        $site = Site::factory()->create([
            'user_id' => $user->id,
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::factory()->create([
            'site_id' => $site->id,
            'endpoint' => '/slow',
            'assertions' => [[
                'type' => AssertionType::MaxTtfbMs->value,
                'value' => 50,
            ]],
        ]);
        $result = new FetchResult(
            statusCode: 200,
            headers: [],
            body: '{"ok":true}',
            responseTimeMs: 200,
            timing: [
                'dns_ms' => 1,
                'connect_ms' => 1,
                'tls_ms' => 1,
                'ttfb_ms' => 180,
                'download_ms' => 20,
                'total_ms' => 200,
            ],
        );

        $evaluated = app(EvaluateAssertionsAction::class)->execute($address, $result);

        $this->assertFalse($evaluated['failed']);
        $this->assertTrue($evaluated['degraded']);
    }

    public function test_failed_assertion_is_persisted_on_snapshot(): void
    {
        $user = $this->actingAsUser();
        $site = Site::factory()->create([
            'user_id' => $user->id,
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::factory()->create([
            'site_id' => $site->id,
            'endpoint' => '/status',
            'assertions' => [[
                'type' => AssertionType::JsonPath->value,
                'path' => '$.status',
                'op' => AssertionOperator::Eq->value,
                'value' => 'ok',
            ]],
        ]);
        $address->setRelation('site', $site);
        Http::fake([
            'https://api.example.com/status' => Http::response(['status' => 'error'], 200),
        ]);

        $run = CheckRun::start($site, CheckRun::SOURCE_SCHEDULE);
        $snapshot = app(SnapshotChecker::class)->check($address, $run->id)['snapshot'];

        $this->assertTrue($snapshot->assertion_failed);
        $this->assertSame(CheckOutcome::Failed, $snapshot->check_outcome);
    }
}
