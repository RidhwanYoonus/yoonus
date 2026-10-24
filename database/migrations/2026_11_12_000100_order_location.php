<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Orders: the customer's internet address and the place it points to (city, region, country). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'order_ip')) {
            Schema::table('orders', function (Blueprint $t) {
                $t->string('order_ip', 45)->nullable();
                $t->string('order_city', 80)->nullable();
                $t->string('order_region', 80)->nullable();
                $t->string('order_country', 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['order_ip', 'order_city', 'order_region', 'order_country'] as $c) {
            if (Schema::hasColumn('orders', $c)) {
                Schema::table('orders', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
    }
};
