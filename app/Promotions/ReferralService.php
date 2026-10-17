<?php

namespace App\Promotions;

use App\Mail\ShopMessage;
use App\Messaging\Messenger;
use App\Models\Coupon;
use App\Models\GiftCard;
use App\Models\Order;
use App\Models\Referral;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Str;

/** "Give AED 50, get AED 50": the friend gets a first-order coupon, the referrer a gift card once that order is paid. */
class ReferralService
{
    public function register(User $referee, ?string $code): ?Referral
    {
        $referrer = $code ? User::where('referral_code', strtoupper($code))->first() : null;
        if (! $referrer || $referrer->id === $referee->id) {
            return null;
        }
        $referee->forceFill(['referred_by_id' => $referrer->id])->save();
        $amount = Settings::get('referral_reward', 50);

        Coupon::create([
            'code' => 'WELCOME-'.Str::upper(Str::random(6)),
            'name' => "Referral welcome AED {$amount}",
            'type' => 'fixed',
            'value' => $amount,
            'scope' => 'cart',
            'first_order_only' => true,
            'max_uses' => 1,
            'issued_to_user_id' => $referee->id,
            'ends_at' => now()->addDays(90),
        ]);

        return Referral::create(['referrer_id' => $referrer->id, 'referee_id' => $referee->id]);
    }

    public function rewardFor(Order $order): void
    {
        if (! $order->user_id) {
            return;
        }
        $referral = Referral::where('referee_id', $order->user_id)->where('status', 'pending')->first();
        if (! $referral) {
            return;
        }
        $card = GiftCard::create([
            'initial_value' => Settings::get('referral_reward', 50),
            'recipient_email' => $referral->referrer->email,
            'recipient_name' => $referral->referrer->name,
            'source' => 'referral',
            'expires_at' => now()->addYear(),
        ]);
        $referral->update(['status' => 'rewarded', 'qualifying_order_id' => $order->id, 'reward_gift_card_id' => $card->id]);

        app(Messenger::class)->email($referral->referrer->email, new ShopMessage(
            __('shop.referral.reward_subject'),
            [__('shop.referral.reward_body', ['amount' => $card->initial_value, 'code' => $card->code])],
            mailLocale: $referral->referrer->locale ?? 'en',
        ), 'referral_reward');
    }
}
