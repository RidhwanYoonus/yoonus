<?php

namespace App\Messaging;

use App\Models\NotificationLog;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * WhatsApp sender via the Twilio API. Without credentials it logs the message
 * (and records it in notification_log), so flows can be tested before go-live.
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

    public function send(?string $phone, string $body, string $template): bool
    {
        $to = self::normalise($phone);
        if (! $to) {
            return false;
        }

        $sid = Settings::get('twilio_sid');
        $token = Settings::get('twilio_token');
        $from = Settings::get('twilio_whatsapp_from');

        try {
            if ($sid && $token && $from) {
                Http::asForm()->withBasicAuth($sid, $token)
                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                        'From' => 'whatsapp:'.self::normalise($from),
                        'To' => 'whatsapp:'.$to,
                        'Body' => $body,
                    ])->throw();
                $status = 'sent';
            } else {
                Log::info("[whatsapp:{$template}] to {$to}: {$body}");
                $status = 'logged';
            }
            NotificationLog::create(['channel' => 'whatsapp', 'recipient' => $to, 'template' => $template, 'status' => $status, 'body' => $body]);

            return true;
        } catch (Throwable $e) {
            NotificationLog::create(['channel' => 'whatsapp', 'recipient' => $to, 'template' => $template, 'status' => 'failed', 'body' => $body, 'error' => $e->getMessage()]);
            report($e);

            return false;
        }
    }

    /** wa.me deep link with a pre-filled message (share / enquire buttons). */
    public static function link(?string $number, string $text): string
    {
        $number = ltrim((string) self::normalise($number), '+');

        return 'https://wa.me/'.$number.'?text='.rawurlencode($text);
    }
}
