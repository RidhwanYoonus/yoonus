<?php

namespace App\Messaging;

use App\Mail\ShopMessage;
use App\Models\NotificationLog;
use Illuminate\Support\Facades\Mail;
use Throwable;

class Messenger
{
    public function __construct(private readonly WhatsApp $whatsapp) {}

    public function email(?string $to, ShopMessage $message, string $template): bool
    {
        if (! $to) {
            return false;
        }
        try {
            Mail::to($to)->send($message);
            NotificationLog::create(['channel' => 'email', 'recipient' => $to, 'template' => $template, 'status' => 'sent', 'body' => $message->subjectLine]);

            return true;
        } catch (Throwable $e) {
            NotificationLog::create(['channel' => 'email', 'recipient' => $to, 'template' => $template, 'status' => 'failed', 'error' => $e->getMessage()]);
            report($e);

            return false;
        }
    }

    public function whatsapp(?string $phone, string $body, string $template): bool
    {
        return $this->whatsapp->send($phone, $body, $template);
    }

    /** Run a callback with the app locale switched (for the recipient's language). */
    public static function inLocale(string $locale, callable $fn): mixed
    {
        $previous = app()->getLocale();
        app()->setLocale($locale);
        try {
            return $fn();
        } finally {
            app()->setLocale($previous);
        }
    }
}
