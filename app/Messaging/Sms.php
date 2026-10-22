<?php

namespace App\Messaging;

use App\Models\NotificationLog;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Plain SMS, for customers without mobile data (e.g. the mobile verification code).
 * Twilio, or any SMS company with a web link: the address with {to} and {message} in it (Settings → WhatsApp → SMS).
 */
class Sms
{
    public static function provider(): ?string
    {
        return match ((string) Settings::get('sms_provider', 'none')) {
            'twilio' => Settings::get('twilio_sid') && Settings::get('twilio_token') && Settings::get('sms_from') ? 'twilio' : null,
            'http' => str_contains((string) Settings::get('sms_http_url'), '{to}') ? 'http' : null,
            default => null,
        };
    }

    public static function configured(): bool
    {
        return self::provider() !== null;
    }

    public function send(?string $phone, string $body, string $template): bool
    {
        $to = WhatsApp::normalise($phone);
        $provider = self::provider();
        if (! $to || ! $provider) {
            return false;
        }
        try {
            if ($provider === 'twilio') {
                Http::asForm()->withBasicAuth(Settings::get('twilio_sid'), Settings::get('twilio_token'))
                    ->post('https://api.twilio.com/2010-04-01/Accounts/'.Settings::get('twilio_sid').'/Messages.json', [
                        'From' => Settings::get('sms_from'), 'To' => $to, 'Body' => $body,
                    ])->throw();
            } else {
                // {to} = 971501234567 (no +), {to_plus} = +971501234567, {message} = the text.
                $url = strtr((string) Settings::get('sms_http_url'), [
                    '{to}' => rawurlencode(ltrim($to, '+')), '{to_plus}' => rawurlencode($to), '{message}' => rawurlencode($body),
                ]);
                (Settings::get('sms_http_method', 'GET') === 'POST' ? Http::timeout(20)->post($url) : Http::timeout(20)->get($url))->throw();
            }
            NotificationLog::create(['channel' => 'sms', 'recipient' => $to, 'template' => $template, 'status' => 'sent', 'body' => $body]);

            return true;
        } catch (Throwable $e) {
            NotificationLog::create(['channel' => 'sms', 'recipient' => $to, 'template' => $template, 'status' => 'failed', 'body' => $body, 'error' => $e->getMessage()]);
            report($e);

            return false;
        }
    }
}
