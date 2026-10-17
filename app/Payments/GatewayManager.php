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
            'card' => $this->card(),
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
        $due = $quote->amountDue / 100;
        $methods = [];
        if ($due > 0) {
            $methods['card'] = ['label' => __('shop.pay.card'), 'note' => __('shop.pay.card_note')];
            foreach (['tabby', 'tamara'] as $bnpl) {
                if (Settings::bool("{$bnpl}_enabled") && $due >= (float) Settings::get("{$bnpl}_min") && $due <= (float) Settings::get("{$bnpl}_max")) {
                    $methods[$bnpl] = [
                        'label' => __("shop.pay.{$bnpl}"),
                        'note' => __("shop.pay.{$bnpl}_note", ['amount' => number_format($due / 4, 2)]),
                    ];
                }
            }
            if (Settings::bool('cod_enabled') && $due <= (float) Settings::get('cod_max_total', 10000) && $quote->fulfilment === 'delivery') {
                $methods['cod'] = ['label' => __('shop.pay.cod'), 'note' => null];
            }
        } else {
            $methods['giftcard'] = ['label' => __('shop.pay.covered'), 'note' => null];
        }

        return $methods;
    }
}
