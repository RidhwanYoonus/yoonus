<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->decimal('flat_rate', 10, 2)->default(0);
            $table->decimal('free_above', 12, 2)->nullable();
            $table->unsignedSmallInteger('eta_days')->default(2);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 32)->nullable();
            $table->string('name');
            $table->string('phone', 32);
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city', 64);
            $table->string('zone_code', 32);
            $table->string('country', 2)->default('AE');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('pending');
            $table->string('payment_method', 16);
            $table->string('payment_status', 16)->default('unpaid');
            $table->string('payment_reference')->nullable();
            $table->string('email');
            $table->string('phone', 32);
            $table->string('customer_name');
            $table->string('buyer_trn', 15)->nullable();
            $table->string('locale', 5)->default('en');
            $table->string('fulfilment', 16)->default('delivery');
            $table->json('shipping_address')->nullable();
            $table->foreignId('shipping_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pickup_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            // Totals are a frozen snapshot; nothing here is ever recalculated from live rates.
            $table->decimal('items_subtotal', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('taxable_amount', 12, 2);
            $table->decimal('vat_total', 12, 2);
            $table->decimal('shipping_total', 12, 2)->default(0);
            $table->decimal('shipping_vat', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2);
            $table->decimal('gift_card_amount', 12, 2)->default(0);
            $table->decimal('amount_due', 12, 2);
            $table->unsignedInteger('loyalty_points_redeemed')->default(0);
            $table->unsignedInteger('loyalty_points_earned')->default(0);
            $table->string('coupon_code')->nullable();
            $table->string('gift_card_code')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_carrier')->nullable();
            $table->text('notes')->nullable();
            $table->string('invoice_number', 32)->nullable()->unique();
            $table->timestamp('invoiced_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->boolean('conversion_sent')->default(false);
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('product_name_ar')->nullable();
            $table->string('sku', 64);
            $table->string('karat', 32);
            $table->decimal('weight_grams', 8, 3);
            $table->unsignedInteger('qty');
            $table->decimal('gold_rate_snapshot', 10, 2);
            $table->decimal('gold_value', 12, 2);
            $table->decimal('making_fee', 12, 2);
            $table->decimal('stone_value', 12, 2);
            $table->decimal('wastage', 12, 2);
            $table->decimal('rounding', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('vat_percent', 5, 2);
            $table->decimal('vat_amount', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->string('coupon_code_applied')->nullable();
            $table->timestamps();
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('order_price_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('old_line_total', 12, 2);
            $table->decimal('new_line_total', 12, 2);
            $table->string('reason');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 16);
            $table->string('reference')->nullable();
            $table->string('status', 16);
            $table->decimal('amount', 12, 2);
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'reference']);
        });

        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->unsignedInteger('last_number')->default(0);
        });
    }

    public function down(): void
    {
        foreach (['invoice_sequences', 'payments', 'order_price_overrides', 'order_status_history', 'order_items', 'orders', 'addresses', 'shipping_zones'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
