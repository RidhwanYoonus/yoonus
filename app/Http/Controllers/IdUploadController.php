<?php

namespace App\Http\Controllers;

use App\Support\IdDocument;
use Illuminate\Http\Request;

/** ID photo sent as soon as it is chosen, so a form that comes back with an error does not lose it. */
class IdUploadController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_if(CheckoutController::idPhotos() === 'hidden', 404);
        $request->validate(['file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:15360']],
            ['file.uploaded' => __('shop.id.upload_failed', ['max' => ini_get('upload_max_filesize')])]);
        $file = $request->file('file');

        return response()->json(['token' => IdDocument::remember($file), 'name' => mb_substr($file->getClientOriginalName(), 0, 80)]);
    }
}
