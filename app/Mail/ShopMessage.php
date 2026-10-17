<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** One branded, bilingual-aware template for every transactional and marketing email. */
class ShopMessage extends Mailable
{
    use Queueable;

    public function __construct(
        public string $subjectLine,
        public array $lines,
        public ?string $actionText = null,
        public ?string $actionUrl = null,
        public string $mailLocale = 'en',
        public ?string $attachmentData = null,
        public ?string $attachmentName = null,
    ) {
        $this->locale($mailLocale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.message');
    }

    public function attachments(): array
    {
        return $this->attachmentData
            ? [Attachment::fromData(fn () => $this->attachmentData, $this->attachmentName)->withMime('application/pdf')]
            : [];
    }
}
