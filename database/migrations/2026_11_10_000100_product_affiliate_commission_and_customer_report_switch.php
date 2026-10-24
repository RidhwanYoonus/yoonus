<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products: one affiliate commission for this piece that replaces every affiliate's own rate (mark-up unchanged).
 * Affiliates: the admin can hide "My customers" (names and contacts) from an affiliate.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'affiliate_commission_type')) {
            Schema::table('products', function (Blueprint $t) {
                $t->string('affiliate_commission_type', 16)->nullable();   // per_piece | making_percent | price_percent
                $t->decimal('affiliate_commission_value', 10, 2)->nullable();
            });
        }
        if (! Schema::hasColumn('affiliates', 'can_see_customers')) {
            Schema::table('affiliates', fn (Blueprint $t) => $t->boolean('can_see_customers')->default(true));
        }
    }

    public function down(): void
    {
        foreach (['products' => ['affiliate_commission_type', 'affiliate_commission_value'], 'affiliates' => ['can_see_customers']] as $table => $cols) {
            foreach ($cols as $c) {
                if (Schema::hasColumn($table, $c)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropColumn($c));
                }
            }
        }
    }
};
