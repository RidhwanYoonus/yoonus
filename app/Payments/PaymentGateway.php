<?php

namespace App\Payments;

use App\Models\Order;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function key(): string;

    /** Create the hosted payment session and return the URL to send the shopper to. */
    public function start(Order $order): string;

    /**
     * Verify a webhook / return callback with the provider and return the paid order, or null.
     * Implementations must confirm the payment server-to-server, never trust query strings alone.
     */
    public function confirm(Request $request): ?Order;
}
