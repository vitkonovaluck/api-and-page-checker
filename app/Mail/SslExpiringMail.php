<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Site;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SslExpiringMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public Site $site,
        public int $daysLeft,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('alerts.mail.ssl_subject', ['site' => $this->site->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.ssl-expiring',
        );
    }
}
