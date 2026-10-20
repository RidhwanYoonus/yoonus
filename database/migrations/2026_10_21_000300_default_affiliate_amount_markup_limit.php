<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Affiliates created before the "amount per piece" mark-up got a maximum of 0; give them the default. */
return new class extends Migration
{
    public function up(): void
    {
        $default = (float) (\App\Support\Settings::get('affiliate_default_max_markup_amount') ?? 100);
        DB::table('affiliates')->where('max_markup_amount', 0)->update(['max_markup_amount' => $default]);
    }

    public function down(): void {}
};
