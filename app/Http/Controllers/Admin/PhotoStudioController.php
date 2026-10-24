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
        ]);
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
