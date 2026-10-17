<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Card / transaction fee collected from the customer at checkout (plus its VAT). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('payment_fee', 12, 2)->default(0)->after('shipping_vat');
            $table->decimal('payment_fee_vat', 12, 2)->default(0)->after('payment_fee');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_fee', 'payment_fee_vat']);
        });
    }
};
