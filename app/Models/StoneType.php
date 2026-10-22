<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/** A stone (Diamond, Emerald, Pearl…) picked on products; one piece can have several. */
class StoneType extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return array<string,string> name => name, for the product form */
    public static function options(): array
    {
        if (! Schema::hasTable('stone_types')) {
            return [];
        }

        return static::where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name', 'name')->all();
    }

    /** Arabic (or English) name for a stone saved on a product. */
    public static function label(string $name, ?string $locale = null): string
    {
        static $ar;
        if (($locale ?? app()->getLocale()) !== 'ar') {
            return $name;
        }
        $ar ??= Schema::hasTable('stone_types') ? static::whereNotNull('name_ar')->pluck('name_ar', 'name')->mapWithKeys(fn ($v, $k) => [mb_strtolower($k) => $v])->all() : [];

        return $ar[mb_strtolower($name)] ?? $name;
    }

    /** The master name for a typed stone, adding it to the list when it is new (CSV import). */
    public static function canonical(string $name): string
    {
        $name = trim($name);
        $found = static::whereRaw('lower(name) = ?', [mb_strtolower($name)])->value('name');

        return $found ?? static::create(['name' => $name, 'sort_order' => (int) static::max('sort_order') + 1])->name;
    }

    public function products()
    {
        return Product::withStone($this->name);
    }
}
