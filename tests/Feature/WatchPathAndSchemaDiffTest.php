<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTOs\DiffOptionsDTO;
use App\Enums\AlertEvent;
use App\Enums\DiffClassification;
use App\Jobs\SendChangeDigestJob;
use App\Mail\ChangeDetectedMail;
use App\Mail\ChangeDigestMail;
use App\Models\Address;
use App\Models\AlertRule;
use App\Models\CheckRun;
use App\Models\NotificationChannel;
use App\Models\Site;
use App\Models\Snapshot;
use App\Services\DiffService;
use App\Services\SnapshotChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WatchPathAndSchemaDiffTest extends TestCase
{
    use RefreshDatabase;

    public function test_watch_path_ignores_unwatched_fields(): void
    {
        $address = Address::factory()->create([
            'watch_json_paths' => ['$.items[*].price'],
        ]);
        $previous = Snapshot::factory()->create([
            'address_id' => $address->id,
            'body' => json_encode([
                'items' => [['price' => 10, 'stock' => 4]],
            ], JSON_THROW_ON_ERROR),
        ]);
        $current = Snapshot::factory()->create([
            'address_id' => $address->id,
            'body' => json_encode([
                'items' => [['price' => 10, 'stock' => 99]],
            ], JSON_THROW_ON_ERROR),
        ]);

        $diff = app(DiffService::class)->compare(
            $previous,
            $current,
            DiffOptionsDTO::fromAddress($address),
        );

        $this->assertFalse($diff['has_changes']);
        $this->assertSame(DiffClassification::None->value, $diff['classification']);
    }

    public function test_price_type_change_is_schema_and_stock_churn_is_value(): void
    {
        $address = Address::factory()->create();
        $previous = Snapshot::factory()->create([
            'address_id' => $address->id,
            'body' => json_encode(['price' => 10, 'stock' => 1], JSON_THROW_ON_ERROR),
        ]);
        $schema = Snapshot::factory()->create([
            'address_id' => $address->id,
            'body' => json_encode(['price' => '10', 'stock' => 1], JSON_THROW_ON_ERROR),
        ]);
        $value = Snapshot::factory()->create([
            'address_id' => $address->id,
            'body' => json_encode(['price' => 10, 'stock' => 2], JSON_THROW_ON_ERROR),
        ]);
        $diffService = app(DiffService::class);
        $options = DiffOptionsDTO::fromAddress($address);

        $schemaDiff = $diffService->compare($previous, $schema, $options);
        $valueDiff = $diffService->compare($previous, $value, $options);

        $this->assertSame(DiffClassification::SchemaChange->value, $schemaDiff['classification']);
        $this->assertSame(DiffClassification::ValueChange->value, $valueDiff['classification']);
    }

    public function test_value_only_changes_can_be_deferred_to_digest(): void
    {
        Mail::fake();
        $user = $this->actingAsUser();
        $site = Site::factory()->create([
            'user_id' => $user->id,
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::factory()->create([
            'site_id' => $site->id,
            'endpoint' => '/stock',
        ]);
        $address->setRelation('site', $site);
        $channel = NotificationChannel::factory()->create(['user_id' => $user->id]);
        AlertRule::factory()->create([
            'site_id' => $site->id,
            'notification_channel_id' => $channel->id,
            'events' => [AlertEvent::ValueChanged->value, AlertEvent::BodyChanged->value],
            'digest_value_changes' => true,
            'notify_on_manual' => false,
        ]);
        Snapshot::factory()->create([
            'address_id' => $address->id,
            'body' => json_encode(['stock' => 1], JSON_THROW_ON_ERROR),
        ]);
        Http::fake([
            'https://api.example.com/stock' => Http::response(['stock' => 2], 200),
        ]);

        $run = CheckRun::start($site, CheckRun::SOURCE_SCHEDULE);
        app(SnapshotChecker::class)->check($address, $run->id);

        Mail::assertNotSent(ChangeDetectedMail::class);

        (new SendChangeDigestJob)->handle(app(DiffService::class));

        Mail::assertSent(ChangeDigestMail::class);
    }
}
