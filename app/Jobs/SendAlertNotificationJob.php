<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AlertChannel;
use App\Mail\ChangeDetectedMail;
use App\Models\AlertRule;
use App\Models\Snapshot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAlertNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $events
     */
    public function __construct(
        public int $alertRuleId,
        public int $snapshotId,
        public array $events,
    ) {}

    public function handle(): void
    {
        $rule = AlertRule::query()->with(['notificationChannel.user', 'site', 'address'])->find($this->alertRuleId);
        $snapshot = Snapshot::query()->with(['address.site'])->find($this->snapshotId);

        if ($rule === null || $snapshot === null || $rule->notificationChannel === null) {
            return;
        }

        $channel = $rule->notificationChannel;

        match ($channel->channel) {
            AlertChannel::Mail => $this->sendMail($rule, $snapshot),
            AlertChannel::Webhook => $this->sendWebhook($rule, $snapshot, $channel->webhookUrl(), $channel->webhookSecret()),
            AlertChannel::Telegram => $this->sendTelegram($rule, $snapshot, $channel->telegramBotToken(), $channel->telegramChatId()),
            default => null,
        };
    }

    private function sendMail(AlertRule $rule, Snapshot $snapshot): void
    {
        $email = $rule->notificationChannel?->emailAddress();

        if ($email === null) {
            return;
        }

        try {
            Mail::to($email)->send(new ChangeDetectedMail($rule, $snapshot, $this->events));
        } catch (Throwable $e) {
            Log::error('Change alert mail failed', [
                'alert_rule_id' => $rule->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendWebhook(AlertRule $rule, Snapshot $snapshot, ?string $url, string $secret): void
    {
        if ($url === null) {
            return;
        }

        $payload = json_encode([
            'event' => 'diff.detected',
            'events' => $this->events,
            'site_id' => $rule->site_id,
            'address_id' => $snapshot->address_id,
            'snapshot_id' => $snapshot->id,
            'status_code' => $snapshot->status_code,
        ], JSON_THROW_ON_ERROR);

        $headers = ['Content-Type' => 'application/json'];

        if ($secret !== '') {
            $headers['X-Api-Checker-Signature'] = hash_hmac('sha256', $payload, $secret);
        }

        try {
            Http::withHeaders($headers)->withBody($payload, 'application/json')->post($url);
        } catch (Throwable $e) {
            Log::error('Change alert webhook failed', [
                'alert_rule_id' => $rule->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendTelegram(AlertRule $rule, Snapshot $snapshot, ?string $token, ?string $chatId): void
    {
        if ($token === null || $chatId === null) {
            return;
        }

        $address = $snapshot->address;
        $text = __('alerts.mail.subject', [
            'site' => $rule->site?->name ?? '',
            'endpoint' => $address?->endpoint ?? '',
        ]);

        try {
            Http::post('https://api.telegram.org/bot'.$token.'/sendMessage', [
                'chat_id' => $chatId,
                'text' => $text,
            ]);
        } catch (Throwable $e) {
            Log::error('Change alert telegram failed', [
                'alert_rule_id' => $rule->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
