<?php

namespace App\Messaging;

use App\Models\NotificationLog;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * WhatsApp sender: Meta's WhatsApp Cloud API (your WhatsApp Business number, set in Settings → Messaging)
 * or Twilio. Without either it logs the message (and records it in notification_log), so flows
 * can be tested before go-live.
 */
class WhatsApp
{
    public static function normalise(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '05') && strlen($digits) === 10) {
            $digits = '971'.substr($digits, 1);     // UAE local mobile
        }

        return '+'.$digits;
    }

    /** Which WhatsApp service is set up: Meta (WhatsApp Cloud API, recommended), Twilio, or none (log only). */
    public static function provider(): ?string
    {
        $choice = (string) Settings::get('whatsapp_provider', 'meta');
        $meta = Settings::get('meta_wa_phone_number_id') && Settings::get('meta_wa_token');
        $twilio = Settings::get('twilio_sid') && Settings::get('twilio_token') && Settings::get('twilio_whatsapp_from');

        return match (true) {
            $choice === 'meta' && $meta => 'meta',
            $choice === 'twilio' && $twilio => 'twilio',
            default => null,
        };
    }

    public static function configured(): bool
    {
        return self::provider() !== null;
    }

    public function send(?string $phone, string $body, string $template): bool
    {
        return $this->deliver($phone, $body, $template, null, null);
    }

    /** Sends a PDF (e.g. the tax invoice). Meta uploads it; Twilio has no upload, so it gets the text with the link. */
    public function sendDocument(?string $phone, string $pdf, string $filename, string $caption, string $template): bool
    {
        return $this->deliver($phone, $caption, $template, $pdf, $filename);
    }

    private function deliver(?string $phone, string $body, string $template, ?string $pdf, ?string $filename): bool
    {
        $to = self::normalise($phone);
        if (! $to) {
            return false;
        }

        try {
            $provider = self::provider();
            if ($provider === 'meta') {
                $this->viaMeta($to, $body, $pdf, $filename);
            } elseif ($provider === 'twilio') {
                Http::asForm()->withBasicAuth(Settings::get('twilio_sid'), Settings::get('twilio_token'))
                    ->post('https://api.twilio.com/2010-04-01/Accounts/'.Settings::get('twilio_sid').'/Messages.json', [
                        'From' => 'whatsapp:'.self::normalise(Settings::get('twilio_whatsapp_from')),
                        'To' => 'whatsapp:'.$to,
                        'Body' => $body,
                    ])->throw();
            } else {
                Log::info("[whatsapp:{$template}] to {$to}: {$body}".($filename ? " [+ {$filename}]" : ''));
            }
            NotificationLog::create(['channel' => 'whatsapp', 'recipient' => $to, 'template' => $template, 'status' => $provider ? 'sent' : 'logged',
                'body' => $body.($filename ? "\n📎 {$filename}" : '')]);

            return true;
        } catch (Throwable $e) {
            NotificationLog::create(['channel' => 'whatsapp', 'recipient' => $to, 'template' => $template, 'status' => 'failed', 'body' => $body, 'error' => $e->getMessage()]);
            report($e);

            return false;
        }
    }

    /**
     * WhatsApp Cloud API. Business-started chats must use a Meta-approved template; when one is set in
     * Settings the message text goes into its {{1}} variable, otherwise it is sent as plain text (which
     * Meta only delivers within 24 h of the customer's last message to you).
     */
    private function viaMeta(string $to, string $body, ?string $pdf, ?string $filename): void
    {
        $base = 'https://graph.facebook.com/'.Settings::get('meta_wa_api_version', 'v21.0').'/'.Settings::get('meta_wa_phone_number_id');
        $http = fn () => Http::withToken((string) Settings::get('meta_wa_token'))->timeout(20);
        $lang = (string) Settings::get('meta_wa_template_lang', 'en');
        $param = ['type' => 'text', 'text' => self::templateText($body)];

        $document = null;
        if ($pdf !== null) {
            $id = $http()->attach('file', $pdf, $filename, ['Content-Type' => 'application/pdf'])
                ->post($base.'/media', ['messaging_product' => 'whatsapp', 'type' => 'application/pdf'])->throw()->json('id');
            $document = ['id' => $id, 'filename' => $filename];
        }

        $invoiceTemplate = Settings::get('meta_wa_invoice_template');
        $textTemplate = Settings::get('meta_wa_template');
        $message = match (true) {
            $document && $invoiceTemplate => ['type' => 'template', 'template' => ['name' => $invoiceTemplate, 'language' => ['code' => $lang], 'components' => [
                ['type' => 'header', 'parameters' => [['type' => 'document', 'document' => $document]]],
                ['type' => 'body', 'parameters' => [$param]],
            ]]],
            $document !== null => ['type' => 'document', 'document' => $document + ['caption' => mb_substr($body, 0, 1000)]],
            (bool) $textTemplate => ['type' => 'template', 'template' => ['name' => $textTemplate, 'language' => ['code' => $lang], 'components' => [
                ['type' => 'body', 'parameters' => [$param]],
            ]]],
            default => ['type' => 'text', 'text' => ['body' => $body, 'preview_url' => true]],
        };

        $http()->post($base.'/messages', ['messaging_product' => 'whatsapp', 'to' => ltrim($to, '+')] + $message)->throw();
    }

    /** Template variables may not contain new lines, tabs or long runs of spaces. */
    public static function templateText(string $text): string
    {
        $flat = preg_replace('/\s*\n+\s*/u', ' · ', trim($text));

        return mb_substr(preg_replace('/[\t ]{2,}/u', ' ', $flat), 0, 1000);
    }

    /** wa.me deep link with a pre-filled message (share / enquire buttons). */
    public static function link(?string $number, string $text): string
    {
        $number = ltrim((string) self::normalise($number), '+');

        return 'https://wa.me/'.$number.'?text='.rawurlencode($text);
    }
}
