<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AlertChannel;
use App\Models\NotificationChannel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationChannel>
 */
class NotificationChannelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'channel' => AlertChannel::Mail,
            'is_enabled' => true,
            'config' => ['email' => 'alerts@example.com'],
        ];
    }
}
