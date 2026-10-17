<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Support\IdDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Streams a customer's ID photo from the private disk — staff only, never cached. */
class IdDocumentController extends Controller
{
    public function __invoke(Request $request, string $owner, int $id, string $side)
    {
        abort_unless($request->user()?->is_admin, 403);
        $model = $owner === 'order' ? Order::findOrFail($id) : User::findOrFail($id);
        $path = $side === 'back' ? $model->id_back_path : $model->id_front_path;
        abort_unless($path && Storage::disk(IdDocument::DISK)->exists($path), 404);

        return Storage::disk(IdDocument::DISK)->response($path, null, [
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
