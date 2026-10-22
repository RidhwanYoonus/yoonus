<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Per affiliate: customers who sign up with this affiliate's code keep the white-page prices (admin can switch it off). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('affiliates', 'signup_markup')) {
            Schema::table('affiliates', fn (Blueprint $t) => $t->boolean('signup_markup')->default(true));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('affiliates', 'signup_markup')) {
            Schema::table('affiliates', fn (Blueprint $t) => $t->dropColumn('signup_markup'));
        }
    }
};
