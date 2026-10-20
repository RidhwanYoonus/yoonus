<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * White-page mark-up per item type, like the commission: by-weight items as a % of making or
 * AED per gram; fixed-price items as a % of price or AED per piece. Maximum 0 = no limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('affiliates', 'making_markup_type')) {
            Schema::table('affiliates', function (Blueprint $table) {
                $table->string('making_markup_type', 10)->default('percent');   // percent | per_gram
                $table->decimal('making_markup_per_gram', 8, 2)->default(0);
                $table->decimal('max_making_markup_per_gram', 8, 2)->default(0);
                $table->string('fixed_markup_type', 10)->default('percent');    // percent | per_piece
                $table->decimal('fixed_markup_amount', 10, 2)->default(0);
                $table->decimal('max_fixed_markup_amount', 10, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::table('affiliates', fn (Blueprint $table) => $table->dropColumn([
            'making_markup_type', 'making_markup_per_gram', 'max_making_markup_per_gram',
            'fixed_markup_type', 'fixed_markup_amount', 'max_fixed_markup_amount',
        ]));
    }
};
