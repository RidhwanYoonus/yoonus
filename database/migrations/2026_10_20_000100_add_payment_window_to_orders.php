<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Unpaid online orders keep their gold price only until payment_expires_at, then they are cancelled. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'payment_expires_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('payment_expires_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_expires_at']);
            $table->dropColumn('payment_expires_at');
        });
    }
};
