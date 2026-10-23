<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin can stop affiliate commission for one customer, and cancel commission not yet earned;
 * cancelled lines stay cancelled when the order later changes status.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'affiliate_commission_off')) {
            Schema::table('users', fn (Blueprint $t) => $t->boolean('affiliate_commission_off')->default(false));
        }
        if (! Schema::hasColumn('affiliate_commissions', 'manual_void')) {
            Schema::table('affiliate_commissions', fn (Blueprint $t) => $t->boolean('manual_void')->default(false));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'affiliate_commission_off')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('affiliate_commission_off'));
        }
        if (Schema::hasColumn('affiliate_commissions', 'manual_void')) {
            Schema::table('affiliate_commissions', fn (Blueprint $t) => $t->dropColumn('manual_void'));
        }
    }
};
