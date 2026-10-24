<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Orders: the delivery / ready date staff promise the customer (set when updating the status). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'expected_at')) {
            Schema::table('orders', fn (Blueprint $t) => $t->date('expected_at')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'expected_at')) {
            Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('expected_at'));
        }
    }
};
