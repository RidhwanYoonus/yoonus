<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('certificate_labs')) {
            Schema::create('certificate_labs', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('short_name', 32)->nullable();
                $table->string('logo')->nullable();
                $table->string('website')->nullable();
                $table->string('verify_url')->nullable();   // with {number} for the certificate number
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
        if (DB::table('certificate_labs')->count() === 0) {
            $now = now();
            DB::table('certificate_labs')->insert(array_map(fn ($row, $i) => $row + ['is_active' => true, 'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now], [
                ['name' => 'Gemological Institute of America', 'short_name' => 'GIA', 'website' => 'https://www.gia.edu', 'verify_url' => 'https://www.gia.edu/report-check?reportno={number}'],
                ['name' => 'International Gemological Institute', 'short_name' => 'IGI', 'website' => 'https://www.igi.org', 'verify_url' => 'https://www.igi.org/verify-your-report/?r={number}'],
                ['name' => 'HRD Antwerp', 'short_name' => 'HRD', 'website' => 'https://www.hrdantwerp.com', 'verify_url' => null],
                ['name' => 'Gemological Science International', 'short_name' => 'GSI', 'website' => 'https://gemscience.net', 'verify_url' => null],
            ], range(1, 4)));
        }

        $columns = [
            'certificate_lab_id' => fn (Blueprint $t) => $t->foreignId('certificate_lab_id')->nullable(),
            'certificate_number' => fn (Blueprint $t) => $t->string('certificate_number', 64)->nullable(),
            'certificate_file' => fn (Blueprint $t) => $t->string('certificate_file')->nullable(),
            'diamond_carat' => fn (Blueprint $t) => $t->decimal('diamond_carat', 8, 3)->nullable(),
            'diamond_shape' => fn (Blueprint $t) => $t->string('diamond_shape', 32)->nullable(),
            'diamond_color' => fn (Blueprint $t) => $t->string('diamond_color', 16)->nullable(),
            'diamond_clarity' => fn (Blueprint $t) => $t->string('diamond_clarity', 16)->nullable(),
            'diamond_cut' => fn (Blueprint $t) => $t->string('diamond_cut', 32)->nullable(),
        ];
        foreach ($columns as $name => $add) {
            if (! Schema::hasColumn('products', $name)) {
                Schema::table('products', fn (Blueprint $t) => $add($t));
            }
        }
    }

    public function down(): void
    {
        foreach (['certificate_lab_id', 'certificate_number', 'certificate_file', 'diamond_carat', 'diamond_shape', 'diamond_color', 'diamond_clarity', 'diamond_cut'] as $c) {
            if (Schema::hasColumn('products', $c)) {
                Schema::table('products', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
        Schema::dropIfExists('certificate_labs');
    }
};
