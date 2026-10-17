<?php

namespace App\Console\Commands;

use App\Mail\ShopMessage;
use App\Messaging\Messenger;
use App\Models\Cart;
use App\Pricing\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

/** Abandoned-cart recovery: email + WhatsApp at 1h, 24h and 72h after last activity. */
class SendAbandonedCartReminders extends Command
{
    protected $signature = 'shop:abandoned-carts';

    protected $description = 'Send abandoned cart reminders (1h, 24h, 72h)';

    public const STAGES = [1 => 1, 2 => 24, 3 => 72];   // reminder number => hours idle

    public function handle(Messenger $messenger): int
    {
        $sent = 0;
        foreach (self::STAGES as $stage => $hours) {
            Cart::query()
                ->whereNull('converted_at')
                ->where('reminders_sent', $stage - 1)
                ->where('last_activity_at', '<=', now()->subHours($hours))
                ->where('last_activity_at', '>=', now()->subDays(7))
                ->where(fn ($q) => $q->whereNotNull('email')->orWhereNotNull('phone'))
                ->chunkById(100, function ($carts) use ($stage, $messenger, &$sent) {
                    foreach ($carts as $cart) {
                        if (empty($cart->items)) {
                            continue;
                        }
                        Messenger::inLocale($cart->locale, function () use ($cart, $stage, $messenger) {
                            $url = URL::signedRoute('cart.recover', ['token' => $cart->token]);
                            $vars = ['total' => Money::format(Money::toFils($cart->last_known_total))];
                            $subject = __("shop.abandoned.stage{$stage}_subject", $vars);
                            $body = __("shop.abandoned.stage{$stage}_body", $vars);
                            $messenger->email($cart->email, new ShopMessage($subject, [$body], __('shop.abandoned.cta'), $url, $cart->locale), "abandoned_{$stage}");
                            $messenger->whatsapp($cart->phone, $subject."\n".$body."\n".$url, "abandoned_{$stage}");
                        });
                        $cart->forceFill(['reminders_sent' => $stage, 'last_reminder_at' => now()])->save();
                        $sent++;
                    }
                });
        }
        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
