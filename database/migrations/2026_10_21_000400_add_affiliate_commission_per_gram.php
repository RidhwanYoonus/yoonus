<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** By-weight items: commission either as a % of the making charge or a fixed AED per gram. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('affiliates', 'making_commission_type')) {
            Schema::table('affiliates', function (Blueprint $table) {
                $table->string('making_commission_type', 10)->default('percent');   // percent | per_gram
                $table->decimal('commission_per_gram', 8, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::table('affiliates', fn (Blueprint $table) => $table->dropColumn(['making_commission_type', 'commission_per_gram']));
    }
};
