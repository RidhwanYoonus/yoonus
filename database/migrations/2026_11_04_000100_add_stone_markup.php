<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** White page: affiliates may also mark up the stone value (within the admin's maximum). */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['stone_markup_percent', 'max_stone_markup_percent'] as $c) {
            if (! Schema::hasColumn('affiliates', $c)) {
                Schema::table('affiliates', fn (Blueprint $t) => $t->decimal($c, 5, 2)->default(0));
            }
        }
        // Part of a line's mark-up that is on the stone (the stone commission is worked out without it).
        if (! Schema::hasColumn('order_items', 'affiliate_stone_markup')) {
            Schema::table('order_items', fn (Blueprint $t) => $t->decimal('affiliate_stone_markup', 12, 2)->default(0));
        }
    }

    public function down(): void
    {
        foreach (['stone_markup_percent', 'max_stone_markup_percent'] as $c) {
            if (Schema::hasColumn('affiliates', $c)) {
                Schema::table('affiliates', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
        if (Schema::hasColumn('order_items', 'affiliate_stone_markup')) {
            Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('affiliate_stone_markup'));
        }
    }
};
