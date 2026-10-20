<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'diamond_origin')) {
            Schema::table('products', fn (Blueprint $t) => $t->string('diamond_origin', 16)->nullable());   // natural | lab_grown
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'diamond_origin')) {
            Schema::table('products', fn (Blueprint $t) => $t->dropColumn('diamond_origin'));
        }
    }
};
