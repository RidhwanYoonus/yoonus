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
        // Guarded so a half-finished earlier run can simply be re-run.
        if (! Schema::hasColumn('products', 'pricing_type')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('pricing_type', 16)->default('GOLD_RATE')->after('gold_category_id');
            });
        }
        if (! Schema::hasColumn('products', 'fixed_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('fixed_price', 12, 2)->nullable()->after('pricing_type');
            });
        }

        try {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('gold_category_id')->nullable()->change();
                $table->decimal('weight_grams', 8, 3)->default(0)->change();
            });
        } catch (\Throwable $e) {
            // Some MariaDB builds refuse to alter a foreign-key column; fixed pricing still works,
            // a karat is just required when creating a product.
            report($e);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['pricing_type', 'fixed_price']);
        });
    }
};
