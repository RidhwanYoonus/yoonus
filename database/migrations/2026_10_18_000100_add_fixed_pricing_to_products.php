<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixed-price products (watches, silver, branded or promotional pieces) that are not
 * priced by weight × gold rate. Karat and weight become optional for those.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('pricing_type', 16)->default('GOLD_RATE')->after('gold_category_id');
            $table->decimal('fixed_price', 12, 2)->nullable()->after('pricing_type');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('gold_category_id')->nullable()->change();
            $table->decimal('weight_grams', 8, 3)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['pricing_type', 'fixed_price']);
        });
    }
};
