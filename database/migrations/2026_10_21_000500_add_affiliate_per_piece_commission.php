<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commission can be the same AED per piece for every item, or set per item type: by-weight
 * items (% of making / AED per gram) and fixed-price items (% of price / AED per piece).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('affiliates', 'commission_mode')) {
            Schema::table('affiliates', function (Blueprint $table) {
                $table->string('commission_mode', 12)->default('by_type');        // by_type | per_piece
                $table->decimal('commission_per_piece', 10, 2)->default(0);       // every item, per piece
                $table->string('fixed_commission_type', 10)->default('percent');  // percent | per_piece
                $table->decimal('fixed_commission_amount', 10, 2)->default(0);    // fixed-price items, per piece
            });
        }
    }

    public function down(): void
    {
        Schema::table('affiliates', fn (Blueprint $table) => $table->dropColumn(['commission_mode', 'commission_per_piece', 'fixed_commission_type', 'fixed_commission_amount']));
    }
};
