<?php

namespace App\Support;

use App\Models\Category;
use App\Models\GoldCategory;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/** Bulk product import/export (CSV, UTF-8, Arabic-safe). Rows are matched on SKU. */
class ProductCsv
{
    public const COLUMNS = [
        'sku', 'name', 'name_ar', 'category', 'karat', 'weight_grams', 'manufacturer_charge_type', 'manufacturer_charge_value',
        'stone_value', 'stone_type', 'wastage_percent', 'stock_qty', 'is_active', 'is_featured', 'hallmark_ref',
        'description', 'description_ar', 'images',
    ];

    public static function export(): \Closure
    {
        return function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // BOM so Excel opens Arabic correctly
            fputcsv($out, [...self::COLUMNS, 'current_price_aed', 'price_calculated_at']);
            Product::with('goldCategory', 'category')->orderBy('sku')->chunk(500, function ($products) use ($out) {
                foreach ($products as $p) {
                    fputcsv($out, [
                        $p->sku, $p->name, $p->name_ar, $p->category?->name, $p->goldCategory->name, $p->weight_grams,
                        $p->manufacturer_charge_type, $p->manufacturer_charge_value, $p->stone_value, $p->stone_type,
                        $p->wastage_percent, $p->stock_qty, (int) $p->is_active, (int) $p->is_featured, $p->hallmark_ref,
                        $p->description, $p->description_ar, implode('|', $p->images ?? []),
                        $p->cached_sales_price, $p->price_calculated_at?->toDateTimeString(),
                    ]);
                }
            });
            fclose($out);
        };
    }

    /** @return array{created:int,updated:int,errors:array<string>} */
    public static function import(string $path): array
    {
        $fh = fopen($path, 'r');
        $header = fgetcsv($fh);
        if (! $header) {
            return ['created' => 0, 'updated' => 0, 'errors' => ['The file is empty.']];
        }
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $h))), $header);
        $karats = GoldCategory::pluck('id', 'name')->mapWithKeys(fn ($id, $n) => [strtoupper($n) => $id]);
        $result = ['created' => 0, 'updated' => 0, 'errors' => []];
        $line = 1;

        DB::transaction(function () use ($fh, $header, $karats, &$result, &$line) {
            while (($row = fgetcsv($fh)) !== false) {
                $line++;
                if (count(array_filter($row, fn ($v) => $v !== null && $v !== '')) === 0) {
                    continue;
                }
                $data = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), null));
                $v = Validator::make($data, [
                    'sku' => 'required|max:64', 'name' => 'required|max:255', 'karat' => 'required',
                    'weight_grams' => 'required|numeric|min:0.001', 'manufacturer_charge_type' => 'nullable|in:PER_PIECE,PERCENTAGE,per_piece,percentage',
                    'manufacturer_charge_value' => 'nullable|numeric|min:0', 'stone_value' => 'nullable|numeric|min:0',
                    'wastage_percent' => 'nullable|numeric|min:0|max:100', 'stock_qty' => 'nullable|integer|min:0',
                ]);
                $karatId = $karats[strtoupper(trim((string) $data['karat']))] ?? null;
                if ($v->fails() || ! $karatId) {
                    $result['errors'][] = "Row {$line}: ".($karatId ? $v->errors()->first() : "unknown karat '{$data['karat']}'");

                    continue;
                }
                $category = filled($data['category'] ?? null)
                    ? Category::firstOrCreate(['slug' => Str::slug($data['category'])], ['name' => $data['category']])
                    : null;

                $product = Product::firstOrNew(['sku' => trim($data['sku'])]);
                $exists = $product->exists;
                $product->fill(array_filter([
                    'name' => $data['name'],
                    'name_ar' => $data['name_ar'] ?? null,
                    'slug' => $product->slug ?: self::uniqueSlug($data['name'], $data['sku']),
                    'category_id' => $category?->id,
                    'gold_category_id' => $karatId,
                    'weight_grams' => $data['weight_grams'],
                    'manufacturer_charge_type' => strtoupper($data['manufacturer_charge_type'] ?: 'PERCENTAGE'),
                    'manufacturer_charge_value' => $data['manufacturer_charge_value'] ?: 0,
                    'stone_value' => $data['stone_value'] ?: 0,
                    'stone_type' => $data['stone_type'] ?? null,
                    'wastage_percent' => $data['wastage_percent'] ?: 0,
                    'stock_qty' => (int) ($data['stock_qty'] ?? 0),
                    'is_active' => isset($data['is_active']) && $data['is_active'] !== '' ? (bool) $data['is_active'] : true,
                    'is_featured' => (bool) ($data['is_featured'] ?? false),
                    'hallmark_ref' => $data['hallmark_ref'] ?? null,
                    'description' => $data['description'] ?? null,
                    'description_ar' => $data['description_ar'] ?? null,
                    'images' => filled($data['images'] ?? null) ? array_values(array_filter(array_map('trim', explode('|', $data['images'])))) : null,
                ], fn ($v) => $v !== null));
                $product->save();
                $exists ? $result['updated']++ : $result['created']++;
            }
        });
        fclose($fh);

        return $result;
    }

    private static function uniqueSlug(string $name, string $sku): string
    {
        $slug = Str::slug($name) ?: Str::slug($sku);

        return Product::where('slug', $slug)->exists() ? $slug.'-'.Str::slug($sku) : $slug;
    }
}
