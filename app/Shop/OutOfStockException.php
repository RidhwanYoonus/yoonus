<?php

namespace App\Shop;

use App\Models\Product;
use RuntimeException;

class OutOfStockException extends RuntimeException
{
    public function __construct(public readonly Product $product)
    {
        parent::__construct(__('shop.checkout.out_of_stock', ['name' => $product->localized('name')]));
    }
}
