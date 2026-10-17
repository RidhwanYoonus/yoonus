<?php

namespace App\Promotions;

use App\Models\LoyaltyEntry;
use App\Models\Order;
use App\Support\Settings;

class LoyaltyService
{
    /** 1 point per AED 10 actually paid on goods (excludes shipping and gift-card tender). */
    public function earn(Order $order): int
    {
        if (! $order->user_id || $order->loyalty_points_earned > 0) {
            return 0;
        }
        $perAed = max(1, (int) Settings::get('loyalty_earn_per_aed', 10));
        $eligible = (float) $order->grand_total - (float) $order->shipping_total - (float) $order->payment_fee - (float) $order->payment_fee_vat - (float) $order->gift_card_amount;
        $points = (int) floor(max(0, $eligible) / $perAed);
        if ($points > 0) {
            LoyaltyEntry::create(['user_id' => $order->user_id, 'order_id' => $order->id, 'points' => $points, 'reason' => 'earned']);
            $order->forceFill(['loyalty_points_earned' => $points])->save();
        }

        return $points;
    }
}
