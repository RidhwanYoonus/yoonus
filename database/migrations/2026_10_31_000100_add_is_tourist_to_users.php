<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Customer said at sign-up (or in My account) that they are a tourist: checkout starts with tax-free chosen. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_tourist')) {
            Schema::table('users', fn (Blueprint $t) => $t->boolean('is_tourist')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'is_tourist')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_tourist'));
        }
    }
};
