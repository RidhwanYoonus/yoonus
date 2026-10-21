<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'tax_free_requested')) {
            Schema::table('orders', fn (Blueprint $t) => $t->boolean('tax_free_requested')->default(false)->index());
        }
        if (! Schema::hasColumn('orders', 'id_country')) {
            Schema::table('orders', fn (Blueprint $t) => $t->string('id_country', 2)->nullable());
        }
        if (! Schema::hasColumn('users', 'id_country')) {
            Schema::table('users', fn (Blueprint $t) => $t->string('id_country', 2)->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'tax_free_requested')) {
            Schema::table('orders', fn (Blueprint $t) => $t->dropIndex(['tax_free_requested']));
        }
        foreach (['orders' => ['tax_free_requested', 'id_country'], 'users' => ['id_country']] as $table => $cols) {
            foreach ($cols as $c) {
                if (Schema::hasColumn($table, $c)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropColumn($c));
                }
            }
        }
    }
};
