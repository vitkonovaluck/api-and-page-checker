<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\AlertRule;
use App\Models\Snapshot;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChangeDetectedMail extends Mailable
{
    use SerializesModels;

    /**
     * @param  list<string>  $events
     */
    public function __construct(
        public AlertRule $rule,
        public Snapshot $snapshot,
        public array $events,
    ) {}

    public function envelope(): Envelope
    {
        $site = $this->rule->site?->name ?? '';
        $endpoint = $this->snapshot->address?->endpoint ?? '';

        return new Envelope(
            subject: __('alerts.mail.subject', ['site' => $site, 'endpoint' => $endpoint]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.change-detected',
        );
    }
}
