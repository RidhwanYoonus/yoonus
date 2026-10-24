<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Photo studio: the original of each photo given a white background, so it can be put back. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'photo_originals')) {
            Schema::table('products', fn (Blueprint $t) => $t->json('photo_originals')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'photo_originals')) {
            Schema::table('products', fn (Blueprint $t) => $t->dropColumn('photo_originals'));
        }
    }
};
