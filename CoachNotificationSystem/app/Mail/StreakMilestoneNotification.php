<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class StreakMilestoneNotification extends Mailable
{
    public function __construct(
        public int $milestone
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Congratulations on your {$this->milestone}-day streak!",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.streak-milestone-notification',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
