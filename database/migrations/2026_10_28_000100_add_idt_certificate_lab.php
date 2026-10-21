<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Adds IDT (idtworldwide.com) to Catalogue → Certificate labs, once. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('certificate_labs') || DB::table('certificate_labs')->where('short_name', 'IDT')->exists()) {
            return;
        }
        DB::table('certificate_labs')->insert([
            'name' => 'IDT Worldwide', 'short_name' => 'IDT', 'website' => 'https://idtworldwide.com',
            'verify_url' => null,   // add the report-check link in the admin if IDT offers one
            'is_active' => true, 'sort_order' => (int) DB::table('certificate_labs')->max('sort_order') + 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('certificate_labs')) {
            DB::table('certificate_labs')->where('short_name', 'IDT')->whereNotExists(fn ($q) => $q->from('products')->whereColumn('products.certificate_lab_id', 'certificate_labs.id'))->delete();
        }
    }
};
