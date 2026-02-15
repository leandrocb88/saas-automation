<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Digest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public Digest $digest,
        public array $videos,
        public string $date,
        public string $shareToken,
        public array $summaryMetrics = []
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your '{$this->digest->name}' Digest - " . $this->date,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.daily-digest',
            with: [
                'user' => $this->user,
                'digest' => $this->digest,
                'videos' => $this->videos,
                'date' => $this->date,
                'shareToken' => $this->shareToken,
                'summaryMetrics' => $this->summaryMetrics,
                'isCustom' => true,
                'title' => $this->digest->name,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
