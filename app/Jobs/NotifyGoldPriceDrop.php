<?php

namespace App\Jobs;

use App\Mail\ShopMessage;
use App\Messaging\Messenger;
use App\Models\GoldPriceAlert;
use App\Models\GoldRateHistory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** "Gold price dropped" alerts for subscribers of a karat (optionally below a target rate). */
class NotifyGoldPriceDrop implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $historyId) {}

    public function handle(Messenger $messenger): void
    {
        $change = GoldRateHistory::with('goldCategory')->find($this->historyId);
        if (! $change || $change->previous_rate === null || (float) $change->rate_per_gram >= (float) $change->previous_rate) {
            return;
        }

        GoldPriceAlert::where('gold_category_id', $change->gold_category_id)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('target_rate')->orWhere('target_rate', '>=', $change->rate_per_gram))
            ->where(fn ($q) => $q->whereNull('last_notified_at')->orWhere('last_notified_at', '<', now()->subHours(12)))
            ->chunkById(200, function ($alerts) use ($change, $messenger) {
                foreach ($alerts as $alert) {
                    Messenger::inLocale($alert->locale, function () use ($alert, $change, $messenger) {
                        $vars = ['karat' => $change->goldCategory->name, 'rate' => $change->rate_per_gram, 'old' => $change->previous_rate];
                        $subject = __('shop.alerts.drop_subject', $vars);
                        $body = __('shop.alerts.drop_body', $vars);
                        $url = route('shop.index', ['karat' => $change->gold_category_id]);
                        $messenger->email($alert->email, new ShopMessage($subject, [$body], __('shop.alerts.cta'), $url, $alert->locale), 'gold_drop');
                        $messenger->whatsapp($alert->phone, $subject."\n".$body."\n".$url, 'gold_drop');
                    });
                    $alert->update(['last_notified_at' => now()]);
                }
            });
    }
}
