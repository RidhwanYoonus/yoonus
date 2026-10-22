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
        'description', 'description_ar', 'images', 'pricing_type', 'fixed_price',
        'low_stock_threshold', 'video_call_enabled', 'meta_title', 'meta_description',
        'diamond_type', 'certificate_lab', 'certificate_number', 'certificate_file',
        'diamond_carat', 'diamond_shape', 'diamond_color', 'diamond_clarity', 'diamond_cut',
        'meta_title_ar', 'meta_description_ar',
    ];

    /** What each column takes, shown in the import window and in the sample file's notes. */
    public const GUIDE = [
        'sku' => 'Required. Matches an existing product (updated) or creates a new one',
        'karat' => '24K / 22K / 21K / 18K… — required for gold-rate items',
        'manufacturer_charge_type' => 'PERCENTAGE or PER_PIECE',
        'pricing_type' => 'GOLD_RATE (weight × rate) or FIXED (fixed_price, excl. VAT)',
        'is_active / is_featured / video_call_enabled' => '1 = yes, 0 = no',
        'images' => 'Image paths or links separated by |',
        'stone_type' => 'One or more stones separated by commas, e.g. Diamond, Emerald (empty = plain gold); a new name adds it to Catalogue → Stone types',
        'diamond_type' => 'natural or lab_grown (empty = no diamond)',
        'certificate_lab' => 'Lab short name or full name from Catalogue → Certificate labs (e.g. GIA, IGI); a new name adds the lab',
        'diamond_shape' => 'Round, Princess, Oval, Emerald, Cushion, Pear, Marquise, Radiant, Asscher, Heart, Baguette, Mixed',
        'diamond_color' => 'D–M, D-F, G-H, I-J or Fancy',
        'diamond_clarity' => 'FL, IF, VVS1, VVS2, VS1, VS2, SI1, SI2, I1–I3',
        'diamond_cut' => 'Excellent, Ideal, Very Good, Good, Fair',
        'meta_title / meta_description (+ _ar)' => 'SEO text; leave empty to have it written from the product details',
    ];

    public const SHAPES = ['Round', 'Princess', 'Oval', 'Emerald', 'Cushion', 'Pear', 'Marquise', 'Radiant', 'Asscher', 'Heart', 'Baguette', 'Mixed'];

    /** Example rows for the "Download sample CSV" button: a gold-rate ring, a natural diamond ring, a lab-grown pendant. */
    public static function sample(): \Closure
    {
        $rows = [
            ['RING-001', 'Classic Band Ring', 'خاتم كلاسيكي', 'Rings', '22K', '5.250', 'PERCENTAGE', '12', '0', '', '0', '3', '1', '0', 'AE-1001',
                '22K gold band, hallmarked.', 'خاتم ذهب عيار 22 مدموغ.', '', 'GOLD_RATE', '', '1', '1', '', '',
                '', '', '', '', '', '', '', '', '', '', ''],
            ['DR-1001', 'Solitaire Diamond Ring', 'خاتم ألماس سوليتير', 'Rings', '18K', '3.100', 'PER_PIECE', '450', '6500', 'Diamond, Emerald', '0', '1', '1', '1', 'AE-2001',
                'Natural diamond solitaire in 18K white gold with GIA certificate.', 'خاتم ألماس طبيعي من ذهب أبيض عيار 18 مع شهادة GIA.', '', 'GOLD_RATE', '', '1', '1', '', '',
                'natural', 'GIA', '2141438171', '', '0.505', 'Round', 'F', 'VS1', 'Excellent', '', ''],
            ['LG-2001', 'Lab-grown Heart Pendant', 'قلادة قلب ألماس مختبري', 'Pendants', '18K', '', 'PERCENTAGE', '0', '0', 'Diamond', '0', '2', '1', '0', '',
                'Lab-grown diamond heart pendant, IGI certified.', 'قلادة قلب ألماس مصنّع في المختبر بشهادة IGI.', '', 'FIXED', '3200', '1', '1', '', '',
                'lab_grown', 'IGI', 'LG612345678', '', '1.000', 'Heart', 'E', 'VVS2', 'Ideal', '', ''],
        ];

        return function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::COLUMNS);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        };
    }

    private const ORIGINS = ['natural' => 'natural', 'natural diamond' => 'natural', 'lab_grown' => 'lab_grown', 'lab grown' => 'lab_grown',
        'lab-grown' => 'lab_grown', 'lab-grown diamond' => 'lab_grown', 'lab grown diamond' => 'lab_grown', 'labgrown' => 'lab_grown', 'lgd' => 'lab_grown'];

    public static function export(): \Closure
    {
        return function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // BOM so Excel opens Arabic correctly
            fputcsv($out, [...self::COLUMNS, 'current_price_aed', 'price_calculated_at']);
            Product::with('goldCategory', 'category', 'certificateLab')->orderBy('sku')->chunk(500, function ($products) use ($out) {
                foreach ($products as $p) {
                    fputcsv($out, [
                        $p->sku, $p->name, $p->name_ar, $p->category?->name, $p->goldCategory?->name, $p->weight_grams,
                        $p->manufacturer_charge_type, $p->manufacturer_charge_value, $p->stone_value, $p->stone_type,
                        $p->wastage_percent, $p->stock_qty, (int) $p->is_active, (int) $p->is_featured, $p->hallmark_ref,
                        $p->description, $p->description_ar, implode('|', $p->images ?? []), $p->pricing_type, $p->fixed_price,
                        $p->low_stock_threshold, (int) $p->video_call_enabled, $p->meta_title, $p->meta_description,
                        $p->diamond_origin, $p->certificateLab?->short_name ?: $p->certificateLab?->name, $p->certificate_number, $p->certificate_file,
                        $p->diamond_carat, $p->diamond_shape, $p->diamond_color, $p->diamond_clarity, $p->diamond_cut,
                        $p->meta_title_ar, $p->meta_description_ar,
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
                $data = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), null))
                    + array_fill_keys(self::COLUMNS, null);
                $fixed = strtoupper(trim((string) ($data['pricing_type'] ?? ''))) === 'FIXED';
                $v = Validator::make($data, [
                    'sku' => 'required|max:64', 'name' => 'required|max:255', 'karat' => $fixed ? 'nullable' : 'required',
                    'weight_grams' => $fixed ? 'nullable|numeric|min:0' : 'required|numeric|min:0.001',
                    'fixed_price' => $fixed ? 'required|numeric|min:0.01' : 'nullable|numeric', 'manufacturer_charge_type' => 'nullable|in:PER_PIECE,PERCENTAGE,per_piece,percentage',
                    'manufacturer_charge_value' => 'nullable|numeric|min:0', 'stone_value' => 'nullable|numeric|min:0',
                    'wastage_percent' => 'nullable|numeric|min:0|max:100', 'stock_qty' => 'nullable|integer|min:0',
                    'low_stock_threshold' => 'nullable|integer|min:0', 'diamond_carat' => 'nullable|numeric|min:0',
                    'certificate_number' => 'nullable|max:64', 'diamond_color' => 'nullable|max:16', 'diamond_clarity' => 'nullable|max:16',
                    'diamond_cut' => 'nullable|max:32', 'diamond_shape' => 'nullable|max:32', 'meta_description' => 'nullable|max:320', 'meta_description_ar' => 'nullable|max:320',
                ]);
                $origin = filled($data['diamond_type'] ?? null) ? (self::ORIGINS[strtolower(trim($data['diamond_type']))] ?? false) : null;
                if ($origin === false) {
                    $result['errors'][] = "Row {$line}: diamond_type must be natural or lab_grown, not '{$data['diamond_type']}'";

                    continue;
                }
                $karatId = $karats[strtoupper(trim((string) $data['karat']))] ?? null;
                if ($v->fails() || (! $karatId && ! $fixed)) {
                    $result['errors'][] = "Row {$line}: ".($karatId ? $v->errors()->first() : "unknown karat '{$data['karat']}'");

                    continue;
                }
                $category = filled($data['category'] ?? null)
                    ? Category::firstOrCreate(['slug' => Str::slug($data['category'])], ['name' => $data['category']])
                    : null;

                $lab = filled($data['certificate_lab'] ?? null) ? self::lab(trim($data['certificate_lab'])) : null;
                $text = fn (string $key) => filled($data[$key] ?? null) ? trim((string) $data[$key]) : null;
                $flag = fn (string $key) => filled($data[$key] ?? null) ? (bool) (int) $data[$key] : null;

                $product = Product::firstOrNew(['sku' => trim($data['sku'])]);
                $exists = $product->exists;
                $product->fill(array_filter([
                    'name' => $data['name'],
                    'name_ar' => $data['name_ar'] ?? null,
                    'slug' => $product->slug ?: self::uniqueSlug($data['name'], $data['sku']),
                    'category_id' => $category?->id,
                    'gold_category_id' => $karatId,
                    'weight_grams' => $data['weight_grams'] ?: 0,
                    'pricing_type' => $fixed ? 'FIXED' : 'GOLD_RATE',
                    'fixed_price' => $fixed ? $data['fixed_price'] : null,
                    'manufacturer_charge_type' => strtoupper($data['manufacturer_charge_type'] ?: 'PERCENTAGE'),
                    'manufacturer_charge_value' => $data['manufacturer_charge_value'] ?: 0,
                    'stone_value' => $data['stone_value'] ?: 0,
                    // Several stones: "Diamond, Emerald" (new names join Catalogue → Stone types).
                    'stone_type' => Product::joinStones(array_map(fn ($s) => \App\Models\StoneType::canonical($s), Product::splitStones($data['stone_type'] ?? null))),
                    'wastage_percent' => $data['wastage_percent'] ?: 0,
                    'stock_qty' => (int) ($data['stock_qty'] ?? 0),
                    'is_active' => isset($data['is_active']) && $data['is_active'] !== '' ? (bool) $data['is_active'] : true,
                    'is_featured' => (bool) ($data['is_featured'] ?? false),
                    'hallmark_ref' => $data['hallmark_ref'] ?? null,
                    'description' => $data['description'] ?? null,
                    'description_ar' => $data['description_ar'] ?? null,
                    'images' => filled($data['images'] ?? null) ? array_values(array_filter(array_map('trim', explode('|', $data['images'])))) : null,
                    'low_stock_threshold' => $text('low_stock_threshold'),
                    'video_call_enabled' => $flag('video_call_enabled'),
                    'meta_title' => $text('meta_title'),
                    'meta_description' => $text('meta_description'),
                    'meta_title_ar' => $text('meta_title_ar'),
                    'meta_description_ar' => $text('meta_description_ar'),
                    'diamond_origin' => $origin,
                    'certificate_lab_id' => $lab?->id,
                    'certificate_number' => $text('certificate_number'),
                    'certificate_file' => $text('certificate_file'),
                    'diamond_carat' => $text('diamond_carat'),
                    'diamond_shape' => ($shape = $text('diamond_shape')) ? (collect(self::SHAPES)->first(fn ($s) => strcasecmp($s, $shape) === 0) ?? $shape) : null,
                    'diamond_color' => ($c = $text('diamond_color')) ? (strcasecmp($c, 'fancy') === 0 ? 'Fancy' : strtoupper($c)) : null,
                    'diamond_clarity' => ($c = $text('diamond_clarity')) ? strtoupper($c) : null,
                    'diamond_cut' => ($c = $text('diamond_cut')) ? ucwords(strtolower($c)) : null,
                ], fn ($v) => $v !== null));
                if ($exists) {
                    // A file with only some columns changes only those columns.
                    $source = ['category_id' => 'category', 'gold_category_id' => 'karat', 'fixed_price' => 'pricing_type', 'diamond_origin' => 'diamond_type', 'certificate_lab_id' => 'certificate_lab'];
                    foreach (array_keys($product->getDirty()) as $field) {
                        if ($field !== 'slug' && ! in_array($source[$field] ?? $field, $header, true)) {
                            $product->setAttribute($field, $product->getOriginal($field));
                        }
                    }
                }
                $product->save();
                $exists ? $result['updated']++ : $result['created']++;
            }
        });
        fclose($fh);

        return $result;
    }

    /** Lab by short name or full name (any case); a name not in the list is added to Catalogue → Certificate labs. */
    private static function lab(string $value): \App\Models\CertificateLab
    {
        return \App\Models\CertificateLab::whereRaw('LOWER(short_name) = ?', [strtolower($value)])
            ->orWhereRaw('LOWER(name) = ?', [strtolower($value)])->first()
            ?? \App\Models\CertificateLab::create(['name' => $value, 'short_name' => mb_strlen($value) <= 10 ? strtoupper($value) : null,
                'is_active' => true, 'sort_order' => (int) \App\Models\CertificateLab::max('sort_order') + 1]);
    }

    private static function uniqueSlug(string $name, string $sku): string
    {
        $slug = Str::slug($name) ?: Str::slug($sku);

        return Product::where('slug', $slug)->exists() ? $slug.'-'.Str::slug($sku) : $slug;
    }
}
