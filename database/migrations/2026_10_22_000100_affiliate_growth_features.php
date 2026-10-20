<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Affiliate growth tools: coupon codes, levels & campaign bonuses, price-lock links, store-visit
 * bookings, in-store sale credit, click tracking and recruiting new affiliates.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('affiliates', 'coupon_discount_percent')) {
            Schema::table('affiliates', function (Blueprint $table) {
                $table->decimal('coupon_discount_percent', 5, 2)->default(0);   // customer discount with the affiliate's code
                $table->boolean('can_lock_price')->default(false);
                $table->foreignId('recruited_by_id')->nullable()->index();
            });
        }
        if (! Schema::hasColumn('coupons', 'affiliate_id')) {
            Schema::table('coupons', fn (Blueprint $table) => $table->foreignId('affiliate_id')->nullable()->index());
        }
        if (! Schema::hasColumn('affiliate_commissions', 'bonus_amount')) {
            Schema::table('affiliate_commissions', function (Blueprint $table) {
                $table->decimal('bonus_amount', 12, 2)->default(0);   // level + booster extra
                $table->string('bonus_note')->nullable();
            });
        }

        // Extra money not tied to one order line: target bonuses, in-store sales, manual corrections.
        if (! Schema::hasTable('affiliate_adjustments')) {
            Schema::create('affiliate_adjustments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
                $table->string('type', 16);                 // bonus | store_sale | manual
                $table->decimal('amount', 12, 2);
                $table->string('status', 12)->default('available');
                $table->string('description');
                $table->json('details')->nullable();
                $table->foreignId('campaign_id')->nullable()->index();
                $table->foreignId('created_by')->nullable();
                $table->timestamps();
                $table->unique(['affiliate_id', 'campaign_id']);
            });
        }
        if (! Schema::hasTable('affiliate_campaigns')) {
            Schema::create('affiliate_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('type', 10);                     // booster | target
                $table->timestamp('starts_at')->nullable();   // nullable: MySQL/MariaDB reject a 2nd timestamp without default
                $table->timestamp('ends_at')->nullable();
                $table->json('affiliate_ids')->nullable();      // null = every affiliate
                $table->foreignId('category_id')->nullable();   // booster: only this category (null = all)
                $table->decimal('multiplier', 4, 2)->default(2); // booster: × commission
                $table->string('target_metric', 8)->default('pieces');   // target: pieces | sales
                $table->decimal('target_value', 12, 2)->default(0);
                $table->decimal('bonus_amount', 10, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('affiliate_price_locks')) {
            Schema::create('affiliate_price_locks', function (Blueprint $table) {
                $table->id();
                $table->string('token', 40)->unique();
                $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->json('price');                          // frozen PriceBreakdown (fils)
                $table->timestamp('expires_at')->nullable();
                $table->unsignedInteger('uses')->default(0);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('store_visits')) {
            Schema::create('store_visits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('affiliate_id')->nullable()->index();
                $table->foreignId('product_id')->nullable();
                $table->foreignId('branch_id')->nullable();
                $table->string('name');
                $table->string('phone', 32);
                $table->timestamp('preferred_at')->nullable();
                $table->text('message')->nullable();
                $table->string('status', 12)->default('new');   // new | confirmed | visited | cancelled
                $table->text('staff_note')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('affiliate_clicks')) {
            Schema::create('affiliate_clicks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('affiliate_id')->index();
                $table->string('type', 12);                     // visit | product
                $table->foreignId('product_id')->nullable();
                $table->boolean('white_page')->default(false);
                $table->string('visitor', 64);
                $table->timestamp('created_at')->nullable()->index();
            });
        }
        if (! Schema::hasTable('affiliate_applications')) {
            Schema::create('affiliate_applications', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email');
                $table->string('phone', 32);
                $table->string('social')->nullable();
                $table->text('message')->nullable();
                $table->foreignId('referred_by_id')->nullable();   // affiliate who shared the join link
                $table->string('status', 12)->default('new');      // new | approved | rejected
                $table->foreignId('affiliate_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['affiliate_applications', 'affiliate_clicks', 'store_visits', 'affiliate_price_locks', 'affiliate_campaigns', 'affiliate_adjustments'] as $t) {
            Schema::dropIfExists($t);
        }
        if (Schema::hasColumn('affiliate_commissions', 'bonus_amount')) {
            Schema::table('affiliate_commissions', fn (Blueprint $table) => $table->dropColumn(['bonus_amount', 'bonus_note']));
        }
        if (Schema::hasColumn('coupons', 'affiliate_id')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->dropIndex(['affiliate_id']);
                $table->dropColumn('affiliate_id');
            });
        }
        if (Schema::hasColumn('affiliates', 'coupon_discount_percent')) {
            Schema::table('affiliates', function (Blueprint $table) {
                $table->dropIndex(['recruited_by_id']);
                $table->dropColumn(['coupon_discount_percent', 'can_lock_price', 'recruited_by_id']);
            });
        }
    }
};
