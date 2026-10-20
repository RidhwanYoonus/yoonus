<?php

namespace App\Support;

/** Applies the email (SMTP or Resend API) details entered in Settings → Email over the .env defaults. */
class MailSettings
{
    public static function apply(): void
    {
        if (Settings::get('mail_from_address')) {
            config(['mail.from.address' => Settings::get('mail_from_address')]);
        }
        config(['mail.from.name' => Settings::get('mail_from_name') ?: Settings::get('store_name', config('mail.from.name'))]);

        if (Settings::get('mail_mailer') === 'resend' && Settings::get('resend_api_key')) {
            config(['mail.default' => 'resend_api', 'mail.mailers.resend_api' => ['transport' => 'resend_api']]);
            self::forget();
            $key = (string) Settings::get('resend_api_key');
            \Illuminate\Support\Facades\Mail::extend('resend_api', fn () => new \App\Mail\ResendTransport($key));

            return;
        }
        if (Settings::get('mail_mailer') !== 'smtp' || ! Settings::get('mail_host')) {
            return;
        }
        $encryption = (string) Settings::get('mail_encryption', 'tls');
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.host' => Settings::get('mail_host'),
            'mail.mailers.smtp.port' => (int) Settings::get('mail_port', $encryption === 'ssl' ? 465 : 587),
            'mail.mailers.smtp.username' => Settings::get('mail_username'),
            'mail.mailers.smtp.password' => Settings::get('mail_password'),
            'mail.mailers.smtp.timeout' => 20,
        ]);
        self::forget();
    }

    /** Settings may change while a queue worker keeps running: drop the cached mailer. */
    private static function forget(): void
    {
        app()->forgetInstance('mail.manager');
        app()->forgetInstance('mailer');
        \Illuminate\Support\Facades\Mail::clearResolvedInstances();
    }
}
