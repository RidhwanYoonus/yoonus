<?php

namespace App\Shop;

use App\Models\Order;
use ArPHP\I18N\Arabic;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

/**
 * UAE FTA tax invoices: supplier TRN, sequential number, per-line VAT breakdown, AED.
 */
class InvoiceService
{
    /** Allocate the next gap-free invoice number for the year (row-locked). */
    public function issue(Order $order): string
    {
        if ($order->invoice_number) {
            return $order->invoice_number;
        }

        return DB::transaction(function () use ($order) {
            $year = (int) now()->format('Y');
            DB::table('invoice_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);
            $seq = DB::table('invoice_sequences')->where('year', $year)->lockForUpdate()->first();
            $next = $seq->last_number + 1;
            DB::table('invoice_sequences')->where('year', $year)->update(['last_number' => $next]);

            $number = sprintf('INV-%d-%06d', $year, $next);
            $order->forceFill(['invoice_number' => $number, 'invoiced_at' => now()])->save();

            return $number;
        });
    }

    public function pdf(Order $order): \Barryvdh\DomPDF\PDF
    {
        $order->loadMissing('items', 'shippingZone', 'pickupBranch');
        $arabic = new Arabic;

        return Pdf::loadView('invoices.tax-invoice', [
            'order' => $order,
            // dompdf cannot join Arabic letters itself; pre-shape any Arabic text.
            'ar' => fn (?string $text) => $text ? $arabic->utf8Glyphs($text) : '',
        ])->setPaper('a4');
    }

    public function filename(Order $order): string
    {
        return ($order->invoice_number ?? $order->number).'.pdf';
    }
}
