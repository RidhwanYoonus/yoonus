<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Affiliates: partners who send customers with their link (or their own "white page" where
 * prices include their mark-up). Commission becomes collectable at the store after delivery.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliates')) {
            Schema::create('affiliates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('code', 32)->unique();
                $table->string('display_name')->nullable();
                $table->decimal('making_commission_percent', 5, 2)->default(0);   // by-gram items: % of making charge
                $table->decimal('fixed_commission_percent', 5, 2)->default(0);    // fixed-price items: % of item total
                $table->boolean('white_page_enabled')->default(false);
                $table->decimal('max_markup_percent', 5, 2)->default(0);          // set by admin
                $table->decimal('markup_percent', 5, 2)->default(0);              // chosen by the affiliate, ≤ max
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('affiliate_commissions')) {
            Schema::create('affiliate_commissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
                $table->string('product_name')->nullable();
                $table->string('basis', 12);                         // making | total
                $table->decimal('base_amount', 12, 2)->default(0);
                $table->decimal('rate_percent', 5, 2)->default(0);
                $table->decimal('commission_amount', 12, 2)->default(0);
                $table->decimal('markup_amount', 12, 2)->default(0);
                $table->decimal('amount', 12, 2)->default(0);        // commission + mark-up
                $table->string('status', 12)->default('pending')->index();   // pending | available | void
                $table->timestamp('available_at')->nullable();
                $table->timestamps();
                $table->unique(['order_item_id']);
            });
        }
        if (! Schema::hasTable('affiliate_payouts')) {
            Schema::create('affiliate_payouts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->timestamp('paid_at');
                $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->string('reference', 64)->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasColumn('orders', 'affiliate_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('affiliate_id')->nullable()->index();
                $table->boolean('affiliate_white_page')->default(false);
            });
            Schema::table('order_items', function (Blueprint $table) {
                $table->decimal('affiliate_markup', 12, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'affiliate_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex(['affiliate_id']);
                $table->dropColumn(['affiliate_id', 'affiliate_white_page']);
            });
            Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('affiliate_markup'));
        }
        Schema::dropIfExists('affiliate_payouts');
        Schema::dropIfExists('affiliate_commissions');
        Schema::dropIfExists('affiliates');
    }
};
