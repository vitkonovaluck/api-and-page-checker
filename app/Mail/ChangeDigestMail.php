<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ChangeDigestMail extends Mailable
{
    use SerializesModels;

    /**
     * @param  Collection<int, array{site: string, endpoint: string, snapshot_id: int}>  $changes
     */
    public function __construct(
        public User $recipient,
        public Collection $changes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('alerts.mail.digest_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.change-digest',
        );
    }
}
