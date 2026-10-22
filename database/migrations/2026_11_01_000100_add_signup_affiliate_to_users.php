<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Customer signed up with an affiliate's code: they keep seeing that affiliate's white-page prices (admin can switch it off). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'signup_affiliate_id')) {
            Schema::table('users', fn (Blueprint $t) => $t->foreignId('signup_affiliate_id')->nullable());
        }
        if (! Schema::hasColumn('users', 'affiliate_markup_on')) {
            Schema::table('users', fn (Blueprint $t) => $t->boolean('affiliate_markup_on')->default(true));
        }
    }

    public function down(): void
    {
        foreach (['signup_affiliate_id', 'affiliate_markup_on'] as $c) {
            if (Schema::hasColumn('users', $c)) {
                Schema::table('users', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
    }
};
