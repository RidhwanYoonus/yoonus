<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** By-gram affiliates can also earn a % of the stone value; every sign-in is timed (affiliates list shows it). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('affiliates', 'stone_commission_percent')) {
            Schema::table('affiliates', fn (Blueprint $t) => $t->decimal('stone_commission_percent', 5, 2)->default(0));
        }
        if (! Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', fn (Blueprint $t) => $t->timestamp('last_login_at')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('affiliates', 'stone_commission_percent')) {
            Schema::table('affiliates', fn (Blueprint $t) => $t->dropColumn('stone_commission_percent'));
        }
        if (Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('last_login_at'));
        }
    }
};
