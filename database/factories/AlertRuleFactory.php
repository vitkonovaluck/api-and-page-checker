<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AlertEvent;
use App\Models\AlertRule;
use App\Models\NotificationChannel;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlertRule>
 */
class AlertRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'address_id' => null,
            'notification_channel_id' => NotificationChannel::factory(),
            'events' => [AlertEvent::BodyChanged->value],
            'min_consecutive' => 1,
            'cooldown_minutes' => 0,
            'notify_on_manual' => false,
            'digest_value_changes' => false,
        ];
    }
}
