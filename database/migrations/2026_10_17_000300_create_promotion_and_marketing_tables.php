<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('slug')->unique();
            $table->string('headline')->nullable();
            $table->string('headline_ar')->nullable();
            $table->string('banner_image')->nullable();
            $table->string('link_url')->nullable();
            $table->boolean('show_countdown')->default(true);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['percentage', 'fixed', 'free_shipping', 'bogo', 'tiered']);
            $table->decimal('value', 10, 2)->default(0);
            // tiered: [{"min":5000,"percent":10}], bogo: {"buy":1,"get":1,"get_percent":100}
            $table->json('rules')->nullable();
            $table->enum('scope', ['cart', 'category', 'products', 'gold_category'])->default('cart');
            $table->json('scope_ids')->nullable();
            $table->boolean('first_order_only')->default(false);
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('max_uses_per_customer')->nullable();
            $table->decimal('min_cart_value', 12, 2)->nullable();
            $table->unsignedInteger('times_used')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('stackable')->default(false);
            $table->boolean('auto_apply')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('issued_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->decimal('discount_amount', 12, 2);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->string('code', 24)->unique();
            $table->decimal('initial_value', 10, 2);
            $table->decimal('balance', 10, 2);
            $table->string('recipient_email')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('message', 500)->nullable();
            $table->string('source', 16)->default('admin');
            $table->foreignId('purchased_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('gift_card_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gift_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('channel', 16)->default('online');
            $table->string('note')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('loyalty_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('points');
            $table->string('reason', 32);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referee_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('qualifying_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('reward_gift_card_id')->nullable()->constrained('gift_cards')->nullOnDelete();
            $table->string('status', 16)->default('pending');
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name');
            $table->unsignedTinyInteger('rating');
            $table->text('body');
            $table->json('photos')->nullable();
            $table->string('status', 16)->default('pending');
            $table->boolean('verified_purchase')->default(false);
            $table->timestamps();
            $table->index(['product_id', 'status']);
        });

        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'product_id']);
        });

        // Persisted carts power abandoned-cart recovery and conversion reporting.
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('locale', 5)->default('en');
            $table->json('items');
            $table->decimal('last_known_total', 12, 2)->default(0);
            $table->unsignedTinyInteger('reminders_sent')->default(0);
            $table->timestamp('last_reminder_at')->nullable();
            $table->timestamp('checkout_started_at')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('last_activity_at');
            $table->timestamps();
            $table->index(['converted_at', 'last_activity_at']);
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('locale', 5)->default('en');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('source', 32)->default('footer');
            $table->timestamps();
        });

        Schema::create('gold_price_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gold_category_id')->constrained()->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('locale', 5)->default('en');
            $table->decimal('target_rate', 10, 2)->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path')->unique();
            $table->string('to_path');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();
        });

        Schema::create('notification_log', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 16);
            $table->string('recipient');
            $table->string('template', 64);
            $table->string('status', 16);
            $table->text('body')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['notification_log', 'redirects', 'gold_price_alerts', 'newsletter_subscribers', 'carts', 'wishlists', 'reviews', 'referrals', 'loyalty_ledger', 'gift_card_transactions', 'gift_cards', 'coupon_redemptions', 'coupons', 'campaigns', 'settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
