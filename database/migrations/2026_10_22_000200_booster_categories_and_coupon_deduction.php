<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Boosters can cover several categories and only by-weight or only fixed-price items.
 * The customer discount of an affiliate's own code is taken from that affiliate's commission.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('affiliate_campaigns', 'category_ids')) {
            Schema::table('affiliate_campaigns', function (Blueprint $table) {
                $table->json('category_ids')->nullable();
                $table->string('item_type', 8)->default('all');   // all | gold | fixed
            });
            foreach (DB::table('affiliate_campaigns')->whereNotNull('category_id')->get(['id', 'category_id']) as $c) {
                DB::table('affiliate_campaigns')->where('id', $c->id)->update(['category_ids' => json_encode([(int) $c->category_id])]);
            }
        }
        if (! Schema::hasColumn('affiliate_commissions', 'coupon_deduction')) {
            Schema::table('affiliate_commissions', fn (Blueprint $table) => $table->decimal('coupon_deduction', 12, 2)->default(0));
        }
    }

    public function down(): void
    {
        Schema::table('affiliate_campaigns', fn (Blueprint $table) => $table->dropColumn(['category_ids', 'item_type']));
        Schema::table('affiliate_commissions', fn (Blueprint $table) => $table->dropColumn('coupon_deduction'));
    }
};
