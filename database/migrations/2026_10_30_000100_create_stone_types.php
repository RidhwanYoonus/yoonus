<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Master list of stone types; a product can hold several (saved as "Diamond, Emerald" in products.stone_type). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stone_types')) {
            Schema::create('stone_types', function (Blueprint $table) {
                $table->id();
                $table->string('name', 80)->unique();
                $table->string('name_ar', 80)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
        // Room for several stones in one piece.
        Schema::table('products', fn (Blueprint $t) => $t->string('stone_type', 255)->nullable()->change());

        $now = now();
        $names = [
            'Diamond' => 'ألماس', 'Lab-grown diamond' => 'ألماس مصنّع مخبرياً', 'Emerald' => 'زمرد', 'Ruby' => 'ياقوت أحمر',
            'Sapphire' => 'ياقوت أزرق', 'Pearl' => 'لؤلؤ', 'Cubic zirconia' => 'زركون', 'Moissanite' => 'مويسانيت',
            'Amethyst' => 'جمشت', 'Topaz' => 'توباز', 'Turquoise' => 'فيروز', 'Opal' => 'أوبال', 'Coral' => 'مرجان',
            'Garnet' => 'عقيق أحمر', 'Onyx' => 'أونيكس', 'Citrine' => 'سترين',
        ];
        // Free-text stones already typed on products join the list too.
        foreach (DB::table('products')->whereNotNull('stone_type')->pluck('stone_type') as $value) {
            foreach (array_filter(array_map('trim', explode(',', (string) $value))) as $name) {
                $names[$name] ??= null;
            }
        }
        $i = 0;
        foreach ($names as $name => $ar) {
            $i++;
            if (! DB::table('stone_types')->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
                DB::table('stone_types')->insert(['name' => $name, 'name_ar' => $ar, 'is_active' => true, 'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stone_types');
    }
};
