<?php

namespace App\Shop;

use App\Mail\ShopMessage;
use App\Messaging\Messenger;
use App\Models\Order;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * One-time handover code: sent to the customer when the order goes out for delivery (or is ready
 * for pickup). Staff enter it to mark the order delivered, proving the receiver is the buyer.
 */
class HandoverOtp
{
    public const MAX_ATTEMPTS = 5;

    public function __construct(private readonly Messenger $messenger) {}

    public static function enabled(): bool
    {
        return Settings::bool('handover_otp');
    }

    public static function required(Order $order): bool
    {
        return self::enabled() && ! $order->handover_verified_at;
    }

    /** The stage at which the code is sent: out for delivery, or ready for pickup. */
    public static function sendsOn(Order $order, string $status): bool
    {
        return $status === ($order->isPickup() ? 'ready' : 'shipped');
    }

    /**
     * Neither email nor WhatsApp can reach the customer (e.g. testing on a PC with MAIL_MAILER=log and
     * no Twilio). Staff are then shown the code so the flow can still be tried end to end.
     */
    public static function testMode(): bool
    {
        return in_array(config('mail.default'), ['log', 'array'], true) && ! \App\Messaging\WhatsApp::configured();
    }

    public static function testCode(Order $order): ?string
    {
        return self::testMode() ? Cache::get("handover-test-code.{$order->id}") : null;
    }

    public function issue(Order $order): void
    {
        $code = (string) random_int(100000, 999999);
        if (self::testMode()) {
            Cache::put("handover-test-code.{$order->id}", $code, now()->addDays(3));
        }
        $order->forceFill([
            'handover_otp_hash' => Hash::make($code),
            'handover_otp_sent_at' => now(),
            'handover_otp_attempts' => 0,
        ])->save();

        Messenger::inLocale($order->locale, function () use ($order, $code) {
            $vars = ['number' => $order->number, 'code' => $code, 'name' => $order->customer_name];
            $key = $order->isPickup() ? 'shop.otp.pickup' : 'shop.otp.delivery';
            $subject = __("{$key}_subject", $vars);
            $body = __("{$key}_body", $vars);
            $this->messenger->email($order->email, new ShopMessage($subject, [$body, __('shop.otp.warning')], mailLocale: $order->locale), 'handover_otp');
            $this->messenger->whatsapp($order->phone, $subject."\n".$body."\n".__('shop.otp.warning'), 'handover_otp');
        });
    }

    /** Checks the code the receiver gives our staff. Throws with a reason staff can act on. */
    public function verify(Order $order, string $code): void
    {
        if (! $order->handover_otp_hash) {
            throw new RuntimeException('No code has been sent for this order yet — press "Send handover code".');
        }
        if ($order->handover_otp_attempts >= self::MAX_ATTEMPTS) {
            throw new RuntimeException('Too many wrong codes. Send a new handover code to the customer.');
        }
        if (! Hash::check(preg_replace('/\D/', '', $code), $order->handover_otp_hash)) {
            $order->increment('handover_otp_attempts');
            $left = self::MAX_ATTEMPTS - $order->handover_otp_attempts;
            throw new RuntimeException("Wrong code — do not hand over the order. {$left} attempt(s) left.");
        }
        $order->forceFill(['handover_verified_via' => 'otp', 'handover_verified_at' => now(), 'handover_otp_hash' => null])->save();
        Cache::forget("handover-test-code.{$order->id}");
    }

    /** Manager override when the customer cannot receive the code (logged with reason). */
    public function override(Order $order, ?User $by, string $reason): void
    {
        $order->forceFill(['handover_verified_via' => 'override', 'handover_verified_at' => now(), 'handover_otp_hash' => null])->save();
        app(OrderService::class)->history($order, $order->status, $order->status, $by, 'Handover code overridden: '.$reason);
    }
}
