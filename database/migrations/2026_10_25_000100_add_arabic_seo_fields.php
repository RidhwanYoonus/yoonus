<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'categories'] as $table) {
            if (! Schema::hasColumn($table, 'meta_title_ar')) {
                Schema::table($table, fn (Blueprint $t) => $t->string('meta_title_ar')->nullable());
            }
            if (! Schema::hasColumn($table, 'meta_description_ar')) {
                Schema::table($table, fn (Blueprint $t) => $t->string('meta_description_ar', 320)->nullable());
            }
        }
    }

    public function down(): void
    {
        foreach (['products', 'categories'] as $table) {
            foreach (['meta_title_ar', 'meta_description_ar'] as $c) {
                if (Schema::hasColumn($table, $c)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropColumn($c));
                }
            }
        }
    }
};
