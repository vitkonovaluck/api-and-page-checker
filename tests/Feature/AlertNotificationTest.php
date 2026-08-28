<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\AcceptBaselineAction;
use App\Enums\AlertChannel;
use App\Enums\AlertEvent;
use App\Enums\ChangeIncidentStatus;
use App\Mail\ChangeDetectedMail;
use App\Models\Address;
use App\Models\AlertRule;
use App\Models\ChangeIncident;
use App\Models\CheckRun;
use App\Models\NotificationChannel;
use App\Models\Site;
use App\Models\Snapshot;
use App\Services\SnapshotChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AlertNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_json_change_sends_email(): void
    {
        Mail::fake();
        $user = $this->actingAsUser();
        [$site, $address, $channel] = $this->monitoredAddress($user->id);
        AlertRule::factory()->create([
            'site_id' => $site->id,
            'notification_channel_id' => $channel->id,
            'events' => [AlertEvent::BodyChanged->value],
            'notify_on_manual' => false,
        ]);
        Snapshot::factory()->create([
            'address_id' => $address->id,
            'body' => json_encode(['v' => 1], JSON_THROW_ON_ERROR),
        ]);
        Http::fake([
            $address->fullUrl() => Http::response(['v' => 2], 200),
        ]);

        $run = CheckRun::start($site, CheckRun::SOURCE_SCHEDULE);
        app(SnapshotChecker::class)->check($address, $run->id);

        Mail::assertSent(ChangeDetectedMail::class);
        $this->assertSame(1, ChangeIncident::query()->where('address_id', $address->id)->count());
    }

    public function test_manual_check_does_not_notify_by_default(): void
    {
        Mail::fake();
        $user = $this->actingAsUser();
        [$site, $address, $channel] = $this->monitoredAddress($user->id);
        AlertRule::factory()->create([
            'site_id' => $site->id,
            'notification_channel_id' => $channel->id,
            'events' => [AlertEvent::BodyChanged->value],
            'notify_on_manual' => false,
        ]);
        Snapshot::factory()->create([
            'address_id' => $address->id,
            'body' => json_encode(['v' => 1], JSON_THROW_ON_ERROR),
        ]);
        Http::fake([
            $address->fullUrl() => Http::response(['v' => 2], 200),
        ]);

        $this->post(route('addresses.check', [$site, $address]))->assertRedirect();

        Mail::assertNothingSent();
    }

    public function test_accept_baseline_stops_repeat_incidents_against_old_snapshot(): void
    {
        Mail::fake();
        $user = $this->actingAsUser();
        [$site, $address, $channel] = $this->monitoredAddress($user->id);
        AlertRule::factory()->create([
            'site_id' => $site->id,
            'notification_channel_id' => $channel->id,
            'events' => [AlertEvent::BodyChanged->value],
            'notify_on_manual' => false,
            'cooldown_minutes' => 0,
        ]);
        $first = Snapshot::factory()->create([
            'address_id' => $address->id,
            'body' => json_encode(['v' => 1], JSON_THROW_ON_ERROR),
        ]);
        $address->forceFill(['baseline_snapshot_id' => $first->id])->save();
        Http::fake([
            $address->fullUrl() => Http::response(['v' => 2], 200),
        ]);

        $run = CheckRun::start($site, CheckRun::SOURCE_SCHEDULE);
        $second = app(SnapshotChecker::class)->check($address, $run->id)['snapshot'];
        Mail::assertSent(ChangeDetectedMail::class, 1);

        app(AcceptBaselineAction::class)->execute($address->fresh(), $second, $user);
        Mail::fake();

        $run2 = CheckRun::start($site, CheckRun::SOURCE_SCHEDULE);
        app(SnapshotChecker::class)->check($address->fresh(), $run2->id);

        Mail::assertNothingSent();
        $this->assertSame(
            0,
            ChangeIncident::query()
                ->where('address_id', $address->id)
                ->where('status', ChangeIncidentStatus::Open)
                ->count(),
        );
    }

    /**
     * @return array{0: Site, 1: Address, 2: NotificationChannel}
     */
    private function monitoredAddress(int $userId): array
    {
        $site = Site::factory()->create([
            'user_id' => $userId,
            'base_url' => 'https://api.example.com',
        ]);
        $address = Address::factory()->create([
            'site_id' => $site->id,
            'endpoint' => '/data',
        ]);
        $address->setRelation('site', $site);
        $channel = NotificationChannel::factory()->create([
            'user_id' => $userId,
            'channel' => AlertChannel::Mail,
            'config' => ['email' => 'alerts@example.com'],
        ]);

        return [$site, $address, $channel];
    }
}
