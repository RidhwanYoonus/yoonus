<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves uploaded photos (storage/app/public) when the public/storage link is missing — some hosts
 * do not allow the link, or it was lost when the files were copied. With the link in place the web
 * server sends the files itself and this is never reached.
 */
class PublicFileController
{
    public function __invoke(string $path): BinaryFileResponse
    {
        $root = realpath(Storage::disk('public')->path(''));
        $file = realpath(Storage::disk('public')->path($path));
        abort_unless($root && $file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file), 404);

        return response()->file($file, ['Cache-Control' => 'public, max-age=2592000, immutable']);
    }
}
