<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\InspectSslCertificateAction;
use App\Enums\AlertChannel;
use App\Enums\AlertEvent;
use App\Mail\SslExpiringMail;
use App\Models\AlertRule;
use App\Models\Site;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class CheckSiteSslCertificatesJob implements ShouldQueue
{
    use Queueable;

    public function handle(InspectSslCertificateAction $inspector): void
    {
        $warnDays = (int) config('checking.ssl_warn_days', 14);

        Site::query()->orderBy('id')->each(function (Site $site) use ($inspector, $warnDays): void {
            $host = $inspector->hostFromSite($site);

            if ($host === null) {
                return;
            }

            $expires = $inspector->execute($host);

            if ($expires === null) {
                return;
            }

            $expiresAt = Carbon::createFromTimestamp($expires);
            $site->forceFill([
                'ssl_expires_at' => $expiresAt,
                'ssl_checked_at' => now(),
            ])->save();

            $daysLeft = (int) now()->startOfDay()->diffInDays($expiresAt, false);

            if ($daysLeft > $warnDays) {
                return;
            }

            $this->notify($site, $daysLeft);
        });
    }

    private function notify(Site $site, int $daysLeft): void
    {
        $rules = AlertRule::query()
            ->where('site_id', $site->id)
            ->with('notificationChannel')
            ->get();

        foreach ($rules as $rule) {
            if (! $rule->watches(AlertEvent::SslExpiring) || $rule->notificationChannel?->channel !== AlertChannel::Mail) {
                continue;
            }

            $email = $rule->notificationChannel->emailAddress();

            if ($email === null) {
                continue;
            }

            try {
                Mail::to($email)->send(new SslExpiringMail($site, $daysLeft));
            } catch (Throwable $e) {
                Log::error('SSL expiry mail failed', [
                    'site_id' => $site->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
