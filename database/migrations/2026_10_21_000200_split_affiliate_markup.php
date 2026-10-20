<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * White-page mark-up: either a % (of the making charge on by-weight items, of the price on
 * fixed-price items) or one AED amount per piece. Each has its own maximum set by the admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('affiliates', 'markup_mode')) {
            Schema::table('affiliates', function (Blueprint $table) {
                $table->string('markup_mode', 10)->default('percent');
                $table->decimal('making_markup_percent', 5, 2)->default(0);
                $table->decimal('fixed_markup_percent', 5, 2)->default(0);
                $table->decimal('markup_amount', 10, 2)->default(0);
                $table->decimal('max_making_markup_percent', 5, 2)->default(0);
                $table->decimal('max_fixed_markup_percent', 5, 2)->default(0);
                $table->decimal('max_markup_amount', 10, 2)->default(0);
            });
            // Carry over the single % used so far.
            DB::table('affiliates')->update([
                'making_markup_percent' => DB::raw('markup_percent'), 'fixed_markup_percent' => DB::raw('markup_percent'),
                'max_making_markup_percent' => DB::raw('max_markup_percent'), 'max_fixed_markup_percent' => DB::raw('max_markup_percent'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('affiliates', fn (Blueprint $table) => $table->dropColumn([
            'markup_mode', 'making_markup_percent', 'fixed_markup_percent', 'markup_amount',
            'max_making_markup_percent', 'max_fixed_markup_percent', 'max_markup_amount',
        ]));
    }
};
