<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Enums\AlertChannel;
use App\Models\NotificationChannel;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

final class NotificationChannels extends Component
{
    public string $channel = AlertChannel::Mail->value;

    public string $email = '';

    public string $webhookUrl = '';

    public string $webhookSecret = '';

    public string $telegramChatId = '';

    public function create(): void
    {
        $validated = $this->validate($this->rules());
        $channel = AlertChannel::from($validated['channel']);

        $this->currentUser()->notificationChannels()->create([
            'channel' => $channel,
            'is_enabled' => true,
            'config' => $this->configFor($channel, $validated),
        ]);

        $this->reset(['email', 'webhookUrl', 'webhookSecret', 'telegramChatId']);
        $this->channel = AlertChannel::Mail->value;
        $this->resetValidation();
    }

    public function toggle(NotificationChannel $channel): void
    {
        $this->assertOwns($channel);

        $channel->forceFill(['is_enabled' => ! $channel->is_enabled])->save();
    }

    public function delete(NotificationChannel $channel): void
    {
        $this->assertOwns($channel);

        $channel->delete();
    }

    public function render(): View
    {
        /** @var Collection<int, NotificationChannel> $channels */
        $channels = $this->currentUser()
            ->notificationChannels()
            ->orderByDesc('id')
            ->get();

        return view('livewire.settings.notification-channels', [
            'channels' => $channels,
            'channelOptions' => AlertChannel::cases(),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rules(): array
    {
        return [
            'channel' => ['required', 'string', Rule::enum(AlertChannel::class)],
            'email' => [
                Rule::requiredIf(fn (): bool => $this->channel === AlertChannel::Mail->value),
                'nullable',
                'email',
                'max:255',
            ],
            'webhookUrl' => [
                Rule::requiredIf(fn (): bool => $this->channel === AlertChannel::Webhook->value),
                'nullable',
                'url',
                'max:2048',
            ],
            'webhookSecret' => ['nullable', 'string', 'max:255'],
            'telegramChatId' => [
                Rule::requiredIf(fn (): bool => $this->channel === AlertChannel::Telegram->value),
                'nullable',
                'string',
                'max:64',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, string>
     */
    private function configFor(AlertChannel $channel, array $validated): array
    {
        return match ($channel) {
            AlertChannel::Mail => ['email' => (string) $validated['email']],
            AlertChannel::Webhook => [
                'webhook_url' => (string) $validated['webhookUrl'],
                'secret' => (string) ($validated['webhookSecret'] ?? ''),
            ],
            AlertChannel::Telegram => ['chat_id' => (string) $validated['telegramChatId']],
        };
    }

    private function assertOwns(NotificationChannel $channel): void
    {
        abort_unless($channel->user_id === $this->currentUser()->id, 403);
    }

    private function currentUser(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
