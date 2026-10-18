<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - products.hidden_after_sale: a piece that sold its last unit is hidden from the shop once that
 *   order is delivered/collected; adding stock shows it again.
 * - users.is_staff + permissions: staff logins with per-area view/create/edit/delete rights.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'hidden_after_sale')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('hidden_after_sale')->default(false)->after('is_active');
            });
        }
        if (! Schema::hasColumn('users', 'is_staff')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_staff')->default(false)->after('is_admin');
                $table->json('permissions')->nullable()->after('is_staff');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['is_staff', 'permissions']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('hidden_after_sale'));
    }
};
