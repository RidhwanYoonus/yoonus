<?php

namespace App\Mail;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

/** Sends email through the Resend API (resend.com) with the API key from Settings → Email. */
class ResendTransport extends AbstractTransport
{
    public const ENDPOINT = 'https://api.resend.com/emails';

    public function __construct(private string $key)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $list = fn (array $addresses) => array_map(fn (Address $a) => $a->toString(), $addresses);
        $envelope = $message->getEnvelope();

        $payload = array_filter([
            'from' => ($email->getFrom()[0] ?? $envelope->getSender())->toString(),
            'to' => $list($email->getTo()),
            'cc' => $list($email->getCc()),
            'bcc' => $list($email->getBcc()),
            'reply_to' => $list($email->getReplyTo()),
            'subject' => (string) $email->getSubject(),
            'html' => $email->getHtmlBody() ? (string) $email->getHtmlBody() : null,
            'text' => $email->getTextBody() ? (string) $email->getTextBody() : null,
            'attachments' => array_map(fn ($part) => array_filter([
                'filename' => $part->getFilename() ?? 'attachment',
                'content' => base64_encode($part->getBody()),
                'content_type' => $part->getMediaType().'/'.$part->getMediaSubtype(),
            ]), $email->getAttachments()),
        ], fn ($v) => $v !== null && $v !== []);

        try {
            $response = Http::withToken($this->key)->acceptJson()->timeout(20)->post(self::ENDPOINT, $payload);
        } catch (\Throwable $e) {
            throw new TransportException('Resend could not be reached: '.$e->getMessage(), 0, $e);
        }
        if (! $response->successful()) {
            throw new TransportException('Resend refused the email ('.$response->status().'): '.($response->json('message') ?? $response->body()));
        }
        if ($id = $response->json('id')) {
            $message->setMessageId((string) $id);
        }
    }

    public function __toString(): string
    {
        return 'resend';
    }
}
