<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products: a video (uploaded file or YouTube / Instagram link). Customers: their Stripe customer, for cards saved
 * at Stripe (never on this server). Orders: the delivery location the customer shared on the map.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['video_path', 'video_url'] as $c) {
            if (! Schema::hasColumn('products', $c)) {
                Schema::table('products', fn (Blueprint $t) => $t->string($c)->nullable());
            }
        }
        if (! Schema::hasColumn('users', 'stripe_customer_id')) {
            Schema::table('users', fn (Blueprint $t) => $t->string('stripe_customer_id', 64)->nullable());
        }
        if (! Schema::hasColumn('orders', 'delivery_lat')) {
            Schema::table('orders', function (Blueprint $t) {
                $t->decimal('delivery_lat', 10, 7)->nullable();
                $t->decimal('delivery_lng', 10, 7)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['products' => ['video_path', 'video_url'], 'users' => ['stripe_customer_id'], 'orders' => ['delivery_lat', 'delivery_lng']] as $table => $cols) {
            foreach ($cols as $c) {
                if (Schema::hasColumn($table, $c)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropColumn($c));
                }
            }
        }
    }
};
