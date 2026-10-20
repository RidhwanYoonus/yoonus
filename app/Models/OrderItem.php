<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Frozen price snapshot of a purchased line; never recalculated from live rates. */
class OrderItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return collect(['weight_grams' => 'decimal:3', 'vat_percent' => 'decimal:2'])
            ->merge(collect(['gold_rate_snapshot', 'gold_value', 'making_fee', 'stone_value', 'wastage', 'rounding', 'subtotal', 'discount_amount', 'vat_amount', 'line_total', 'affiliate_markup'])
                ->mapWithKeys(fn ($c) => [$c => 'decimal:2']))
            ->all();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function localizedName(): string
    {
        return app()->getLocale() === 'ar' && $this->product_name_ar ? $this->product_name_ar : $this->product_name;
    }
}
