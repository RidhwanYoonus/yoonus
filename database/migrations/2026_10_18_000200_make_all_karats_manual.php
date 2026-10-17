<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Every karat's rate is set by hand; a 24K change no longer moves 22K/21K/18K/14K. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('gold_categories')->update(['auto_derive' => false, 'base_category_id' => null]);
    }

    public function down(): void
    {
        // Intentionally left empty: derivation can be re-enabled per karat in the admin.
    }
};
