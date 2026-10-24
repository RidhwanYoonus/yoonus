<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Images;
use App\Support\StaffAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Photo studio: the piece is cut out in the admin's own browser (free AI model, nothing is sent to another
 * company), placed on white with a soft shadow, and saved here in place of the photo. The original is kept.
 */
class PhotoStudioController extends Controller
{
    private function product(Request $request, Product $product): Product
    {
        abort_unless(StaffAccess::can($request->user(), 'products', 'edit'), 403);

        return $product;
    }

    public function show(Request $request, Product $product)
    {
        $product = $this->product($request, $product);
        $originals = (array) ($product->photo_originals ?? []);

        return view('admin.photo-studio', [
            'product' => $product,
            'photos' => collect((array) ($product->images ?? []))->values()->map(fn ($path, $i) => [
                'index' => $i, 'path' => $path, 'url' => Storage::disk('public')->url($path),
                'original' => isset($originals[$path]) ? Storage::disk('public')->url($originals[$path]) : null,
            ])->all(),
            'back' => \App\Filament\Resources\ProductResource::getUrl('edit', ['record' => $product]),
            'backgrounds' => self::backgrounds(),
            'models' => array_map(fn ($p) => ['path' => $p, 'url' => Storage::disk('public')->url($p)], self::added('studio_models')),
        ]);
    }

    /** "On a model": the finished picture is added as an extra photo of the product (the others stay). */
    public function addPhoto(Request $request, Product $product)
    {
        $product = $this->product($request, $product);
        $request->validate(['file' => 'required|file|mimes:jpg,jpeg,png,webp|max:15360']);
        $images = array_values((array) ($product->images ?? []));
        abort_if(count($images) >= 12, 422, 'This product already has 12 photos. Remove one first.');
        $new = Images::storeWebp($request->file('file'), 'products', 2000, 88);
        $images[] = $new;
        $product->forceFill(['images' => $images])->save();

        return response()->json(['path' => $new, 'url' => Storage::disk('public')->url($new), 'index' => count($images) - 1]);
    }

    /** Model photos (a neck, an ear, a hand…) kept for the "On a model" pictures. */
    public function addModel(Request $request)
    {
        abort_unless(StaffAccess::can($request->user(), 'products', 'edit'), 403);
        $request->validate(['file' => 'required|file|mimes:jpg,jpeg,png,webp,heic|max:15360']);
        $path = Images::storeWebp($request->file('file'), 'branding/models', 2000, 88);
        \App\Support\Settings::set(['studio_models' => array_slice([...self::added('studio_models'), $path], -24)]);

        return response()->json(['path' => $path, 'url' => Storage::disk('public')->url($path)]);
    }

    public function removeModel(Request $request)
    {
        abort_unless(StaffAccess::can($request->user(), 'products', 'edit'), 403);
        $path = (string) $request->validate(['path' => 'required|string'])['path'];
        $list = self::added('studio_models');
        if (in_array($path, $list, true)) {
            Storage::disk('public')->delete($path);
            \App\Support\Settings::set(['studio_models' => array_values(array_diff($list, [$path]))]);
        }

        return response()->json(['ok' => true]);
    }

    /** Store photos to use as a background: the showroom / home photos from Settings, plus the ones added here. */
    public static function backgrounds(): array
    {
        $list = [];
        foreach (['showroom_image' => 'Showroom photo (Settings)', 'home_hero_image' => 'Home page photo (Settings)'] as $key => $label) {
            if ($url = \App\Support\Settings::imageUrl($key)) {
                $list[] = ['url' => $url, 'label' => $label, 'path' => null];
            }
        }
        foreach (self::added() as $path) {
            $list[] = ['url' => Storage::disk('public')->url($path), 'label' => 'Store photo', 'path' => $path];
        }

        return $list;
    }

    private static function added(string $key = 'studio_backgrounds'): array
    {
        $v = \App\Support\Settings::get($key, []);

        return array_values(array_filter(is_string($v) ? (json_decode($v, true) ?: []) : (array) $v, 'is_string'));
    }

    public function addBackground(Request $request)
    {
        abort_unless(StaffAccess::can($request->user(), 'products', 'edit'), 403);
        $request->validate(['file' => 'required|file|mimes:jpg,jpeg,png,webp,heic|max:15360']);
        $path = Images::storeWebp($request->file('file'), 'branding/studio', 1800, 85);
        \App\Support\Settings::set(['studio_backgrounds' => array_slice([...self::added(), $path], -12)]);

        return response()->json(['path' => $path, 'url' => Storage::disk('public')->url($path)]);
    }

    public function removeBackground(Request $request)
    {
        abort_unless(StaffAccess::can($request->user(), 'products', 'edit'), 403);
        $path = (string) $request->validate(['path' => 'required|string'])['path'];
        $list = self::added();
        if (in_array($path, $list, true)) {
            Storage::disk('public')->delete($path);
            \App\Support\Settings::set(['studio_backgrounds' => array_values(array_diff($list, [$path]))]);
        }

        return response()->json(['ok' => true]);
    }

    /** One finished photo: replaces photo number {index}; the photo it replaces is kept as its original. */
    public function save(Request $request, Product $product)
    {
        $product = $this->product($request, $product);
        $data = $request->validate(['index' => 'required|integer|min:0', 'path' => 'required|string', 'file' => 'required|file|mimes:jpg,jpeg,png,webp|max:15360']);
        $images = array_values((array) ($product->images ?? []));
        abort_unless(($images[$data['index']] ?? null) === $data['path'], 409, 'The photos changed meanwhile; open the studio again.');

        $new = Images::storeWebp($request->file('file'), 'products', 2000, 88);
        $originals = (array) ($product->photo_originals ?? []);
        // A photo done twice still points back to the very first original.
        $originals[$new] = $originals[$data['path']] ?? $data['path'];
        unset($originals[$data['path']]);
        $images[$data['index']] = $new;
        $product->forceFill(['images' => $images, 'photo_originals' => $originals])->save();

        return response()->json(['path' => $new, 'url' => Storage::disk('public')->url($new)]);
    }

    /** Puts the original photo back. */
    public function restore(Request $request, Product $product)
    {
        $product = $this->product($request, $product);
        $data = $request->validate(['index' => 'required|integer|min:0', 'path' => 'required|string']);
        $images = array_values((array) ($product->images ?? []));
        $originals = (array) ($product->photo_originals ?? []);
        abort_unless(($images[$data['index']] ?? null) === $data['path'] && isset($originals[$data['path']]), 409);

        Storage::disk('public')->delete($data['path']);
        $images[$data['index']] = $originals[$data['path']];
        unset($originals[$data['path']]);
        $product->forceFill(['images' => $images, 'photo_originals' => $originals ?: null])->save();

        return response()->json(['path' => $images[$data['index']], 'url' => Storage::disk('public')->url($images[$data['index']])]);
    }
}
