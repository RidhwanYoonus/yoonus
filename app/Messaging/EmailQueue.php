<?php

namespace App\Messaging;

use App\Mail\ShopMessage;
use App\Models\NotificationLog;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Daily email limit (many hosts allow only ~100 emails a day). Over the limit, ordinary emails wait in
 * queued_emails and go out the next day; order emails (with the invoice) and sign-in / verification codes
 * are always sent at once.
 */
class EmailQueue
{
    /** Templates that never wait: order updates with the invoice and one-time codes. */
    public static function urgent(string $template): bool
    {
        return str_starts_with($template, 'order_')
            || in_array($template, ['handover_otp', 'phone_verification', 'admin_two_factor', 'admin_new_device', 'video_call_link', 'password_reset', 'account_created', 'login_code'], true);
    }

    public static function limit(): int
    {
        return max(0, (int) Settings::get('email_daily_limit', 0));
    }

    public static function sentToday(): int
    {
        $start = now(config('app.timezone'))->startOfDay()->utc();

        return NotificationLog::where('channel', 'email')->whereIn('status', ['sent', 'logged'])->where('created_at', '>=', $start)->count();
    }

    /** True when this email must wait until tomorrow. */
    public static function shouldHold(string $template): bool
    {
        return self::limit() > 0 && ! self::urgent($template) && Schema::hasTable('queued_emails') && self::sentToday() >= self::limit();
    }

    public static function hold(string $to, ShopMessage $message, string $template): void
    {
        DB::table('queued_emails')->insert([
            'to' => $to, 'template' => $template, 'subject' => mb_substr($message->subjectLine, 0, 250),
            'payload' => base64_encode(serialize($message)), 'created_at' => now(), 'updated_at' => now(),
        ]);
        NotificationLog::create(['channel' => 'email', 'recipient' => $to, 'template' => $template, 'status' => 'queued', 'body' => $message->subjectLine]);
    }

    public static function waiting(): int
    {
        return Schema::hasTable('queued_emails') ? DB::table('queued_emails')->whereNull('sent_at')->count() : 0;
    }

    /** Send waiting emails, oldest first, while today's limit allows. Returns how many went out. */
    public static function flush(): int
    {
        if (! Schema::hasTable('queued_emails')) {
            return 0;
        }
        $room = self::limit() > 0 ? max(0, self::limit() - self::sentToday()) : PHP_INT_MAX;
        $sent = 0;
        foreach (DB::table('queued_emails')->whereNull('sent_at')->orderBy('id')->limit(min($room, 200))->get() as $row) {
            try {
                $message = unserialize(base64_decode($row->payload), ['allowed_classes' => [ShopMessage::class]]);
                Mail::to($row->to)->send($message);
                DB::table('queued_emails')->where('id', $row->id)->update(['sent_at' => now(), 'updated_at' => now()]);
                NotificationLog::create(['channel' => 'email', 'recipient' => $row->to, 'template' => $row->template, 'status' => config('mail.default') === 'log' ? 'logged' : 'sent', 'body' => $row->subject]);
                $sent++;
            } catch (Throwable $e) {
                DB::table('queued_emails')->where('id', $row->id)->update(['error' => mb_substr($e->getMessage(), 0, 490), 'updated_at' => now()]);
            }
        }

        return $sent;
    }
}
