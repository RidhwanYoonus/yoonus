<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Converts uploads to WebP (smaller, faster on 4G) and stores them on the public disk. */
class Images
{
    public static function storeWebp(UploadedFile $file, string $dir, int $maxWidth = 1600, int $quality = 82): string
    {
        $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $src) {
            return $file->store($dir, 'public');
        }
        $w = imagesx($src);
        $h = imagesy($src);
        if ($w > $maxWidth) {
            $nh = (int) round($h * $maxWidth / $w);
            $dst = imagecreatetruecolor($maxWidth, $nh);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxWidth, $nh, $w, $h);
            imagedestroy($src);
            $src = $dst;
        }
        ob_start();
        imagewebp($src, null, $quality);
        $data = ob_get_clean();
        imagedestroy($src);

        $path = trim($dir, '/').'/'.Str::random(32).'.webp';
        Storage::disk('public')->put($path, $data);

        return $path;
    }

    /** Convert an already-stored file (e.g. a Filament upload) to WebP in place. */
    public static function convertStored(string $path, string $disk = 'public'): string
    {
        if (str_ends_with(strtolower($path), '.webp') || ! Storage::disk($disk)->exists($path)) {
            return $path;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, Storage::disk($disk)->get($path));
        $new = self::storeWebp(new UploadedFile($tmp, basename($path), null, null, true), dirname($path));
        if ($new !== $path && str_ends_with($new, '.webp')) {
            Storage::disk($disk)->delete($path);
        }
        @unlink($tmp);

        return $new;
    }
}
