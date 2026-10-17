<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MASTER: gold categories (24K / 22K / 21K / 18K, extendable).
        Schema::create('gold_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 32)->unique();
            $table->decimal('karat', 5, 2);
            $table->decimal('purity_percent', 5, 2);
            $table->decimal('rate_per_gram', 10, 2)->default(0);
            $table->boolean('auto_derive')->default(false);
            $table->foreignId('base_category_id')->nullable()->constrained('gold_categories')->nullOnDelete();
            $table->timestamp('rate_updated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Full audit trail: rows are never updated or deleted.
        Schema::create('gold_rate_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gold_category_id')->constrained()->cascadeOnDelete();
            $table->decimal('previous_rate', 10, 2)->nullable();
            $table->decimal('rate_per_gram', 10, 2);
            $table->string('input_unit', 8)->default('gram');
            $table->decimal('input_value', 12, 2)->nullable();
            $table->dateTime('effective_from');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 32)->default('manual');
            $table->string('recalc_batch_id', 64)->nullable();
            $table->unsignedInteger('affected_products')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->index(['gold_category_id', 'effective_from']);
        });

        Schema::create('vat_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('vat_percent', 5, 2)->default(5.00);
            $table->foreignId('gold_category_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('image')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('sku', 64)->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->json('images')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('gold_category_id')->constrained()->restrictOnDelete();
            $table->decimal('weight_grams', 8, 3);
            $table->enum('manufacturer_charge_type', ['PER_PIECE', 'PERCENTAGE'])->default('PERCENTAGE');
            $table->decimal('manufacturer_charge_value', 10, 2)->default(0);
            $table->decimal('stone_value', 10, 2)->default(0);
            $table->string('stone_type', 64)->nullable();
            $table->decimal('wastage_percent', 5, 2)->default(0);
            $table->string('hallmark_ref', 64)->nullable();
            $table->unsignedInteger('stock_qty')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(2);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            // Price cache, refreshed by the recalculation job. Reads always recompute.
            $table->decimal('cached_gold_value', 12, 2)->nullable();
            $table->decimal('cached_making_fee', 12, 2)->nullable();
            $table->decimal('cached_subtotal', 12, 2)->nullable();
            $table->decimal('cached_vat', 12, 2)->nullable();
            $table->decimal('cached_sales_price', 12, 2)->nullable();
            $table->decimal('rate_snapshot', 10, 2)->nullable();
            $table->timestamp('price_calculated_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'gold_category_id']);
            $table->index(['is_active', 'cached_sales_price']);
        });

        Schema::create('price_recalc_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gold_rate_history_id')->nullable()->constrained('gold_rate_history')->nullOnDelete();
            $table->decimal('old_price', 12, 2)->nullable();
            $table->decimal('new_price', 12, 2);
            $table->decimal('rate_used', 10, 2);
            $table->string('reason', 32)->default('rate_change');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('emirate', 32);
            $table->string('address');
            $table->string('phone', 32)->nullable();
            $table->string('opening_hours')->nullable();
            $table->boolean('pickup_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('branch_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('qty')->default(0);
            $table->unique(['branch_id', 'product_id']);
        });
    }

    public function down(): void
    {
        foreach (['branch_stock', 'branches', 'price_recalc_log', 'products', 'categories', 'vat_settings', 'gold_rate_history', 'gold_categories'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
