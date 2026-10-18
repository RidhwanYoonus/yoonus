<?php

namespace App\Payments;

use App\Shop\Quote;
use App\Support\Settings;
use InvalidArgumentException;

class GatewayManager
{
    public function for(string $method): PaymentGateway
    {
        return match ($method) {
            // Apple Pay / Google Pay run through the card gateway's secure page (Telr / Stripe).
            'card', 'applepay', 'googlepay' => $this->card(),
            'tabby' => app(TabbyGateway::class),
            'tamara' => app(TamaraGateway::class),
            'mock', 'stripe', 'telr' => $this->card($method),
            default => throw new InvalidArgumentException("Unknown payment method [$method]."),
        };
    }

    public function card(?string $driver = null): PaymentGateway
    {
        return match ($driver ?? Settings::get('card_gateway', 'mock')) {
            'stripe' => app(StripeGateway::class),
            'telr' => app(TelrGateway::class),
            default => app(MockGateway::class),
        };
    }

    /**
     * Payment methods offered for this quote. BNPL (Tabby/Tamara) only appears
     * when the amount due sits inside the provider's limits.
     *
     * @return array<string,array{label:string,note:?string}>
     */
    public function available(Quote $quote): array
    {
        $due = $quote->amountBeforeFee / 100;
        $methods = [];
        if ($due > 0) {
            foreach (['applepay', 'googlepay'] as $wallet) {
                if (Settings::bool("{$wallet}_enabled")) {
                    $methods[$wallet] = ['label' => __("shop.pay.{$wallet}"), 'note' => $this->withFee(__("shop.pay.{$wallet}_note"), 'card')];
                }
            }
            $methods['card'] = ['label' => __('shop.pay.card'), 'note' => $this->withFee(__('shop.pay.card_note'), 'card')];
            foreach (['tabby', 'tamara'] as $bnpl) {
                if (Settings::bool("{$bnpl}_enabled") && $due >= (float) Settings::get("{$bnpl}_min") && $due <= (float) Settings::get("{$bnpl}_max")) {
                    $methods[$bnpl] = [
                        'label' => __("shop.pay.{$bnpl}"),
                        'note' => $this->withFee(__("shop.pay.{$bnpl}_note", ['amount' => number_format($due / 4, 2)]), $bnpl),
                    ];
                }
            }
            if (Settings::bool('cod_enabled') && $due <= (float) Settings::get('cod_max_total', 10000) && $quote->fulfilment === 'delivery') {
                $methods['cod'] = ['label' => __('shop.pay.cod'), 'note' => $this->withFee(null, 'cod')];
            }
        } else {
            $methods['giftcard'] = ['label' => __('shop.pay.covered'), 'note' => null];
        }

        return $methods;
    }

    /** Append "+ 2.5% card fee" style text when a fee is configured for the method. */
    private function withFee(?string $note, string $method): ?string
    {
        $pct = (float) Settings::get("{$method}_fee_percent", 0);
        $fixed = (float) Settings::get("{$method}_fee_fixed", 0);
        if ($pct <= 0 && $fixed <= 0) {
            return $note;
        }
        $parts = array_filter([$pct > 0 ? rtrim(rtrim(number_format($pct, 2), '0'), '.').'%' : null, $fixed > 0 ? 'AED '.number_format($fixed, 2) : null]);
        $fee = __('shop.pay.fee_note', ['fee' => implode(' + ', $parts)]);

        return $note ? "{$note} · {$fee}" : $fee;
    }
}
