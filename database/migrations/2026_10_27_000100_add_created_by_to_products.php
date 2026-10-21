<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['created_by', 'updated_by'] as $c) {
            if (! Schema::hasColumn('products', $c)) {
                Schema::table('products', fn (Blueprint $t) => $t->foreignId($c)->nullable()->index());
            }
        }
    }

    public function down(): void
    {
        foreach (['created_by', 'updated_by'] as $c) {
            if (Schema::hasColumn('products', $c)) {
                Schema::table('products', function (Blueprint $t) use ($c) {
                    $t->dropIndex([$c]);
                    $t->dropColumn($c);
                });
            }
        }
    }
};
