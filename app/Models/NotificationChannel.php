<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AlertChannel;
use Database\Factories\NotificationChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'channel',
    'is_enabled',
    'config',
])]
class NotificationChannel extends Model
{
    /** @use HasFactory<NotificationChannelFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'channel' => AlertChannel::class,
            'is_enabled' => 'boolean',
            'config' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function alertRules(): HasMany
    {
        return $this->hasMany(AlertRule::class);
    }

    public function emailAddress(): ?string
    {
        $email = $this->config['email'] ?? null;

        return is_string($email) && $email !== '' ? $email : $this->user?->email;
    }

    public function webhookUrl(): ?string
    {
        $url = $this->config['webhook_url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function webhookSecret(): string
    {
        $secret = $this->config['secret'] ?? '';

        return is_string($secret) ? $secret : '';
    }

    public function telegramChatId(): ?string
    {
        $chatId = $this->config['chat_id'] ?? null;

        return is_string($chatId) && $chatId !== '' ? $chatId : null;
    }

    public function telegramBotToken(): ?string
    {
        $token = $this->config['bot_token'] ?? config('services.telegram.bot_token');

        return is_string($token) && $token !== '' ? $token : null;
    }
}
