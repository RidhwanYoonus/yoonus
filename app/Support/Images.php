<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shrinks uploaded photos: phone pictures (often 5–15 MB) become a few hundred KB.
 * Turns them the right way up (phone EXIF rotation), limits the longest side and re-compresses.
 */
class Images
{
    /** Product, review and banner photos: WebP on the public disk. */
    public static function storeWebp(UploadedFile $file, string $dir, int $maxSide = 2000, int $quality = 82): string
    {
        $img = self::load($file->getRealPath(), $maxSide);
        if (! $img) {
            return $file->store($dir, 'public');
        }
        // Some servers' PHP cannot write WebP (missing or empty output): use JPEG then, never an empty file.
        $ext = 'webp';
        ob_start();
        $ok = function_exists('imagewebp') && @imagewebp($img, null, $quality);
        $data = ob_get_clean();
        if (! $ok || strlen((string) $data) < 100) {
            ob_start();
            imagejpeg($img, null, 85);
            $data = ob_get_clean();
            $ext = 'jpg';
        }
        imagedestroy($img);

        $path = trim($dir, '/').'/'.Str::random(32).'.'.$ext;
        Storage::disk('public')->put($path, $data);

        return $path;
    }

    /** ID photos: JPEG, readable but small. PDFs and anything that is not a photo are stored as they are. */
    public static function storeJpeg(UploadedFile $file, string $dir, string $disk, int $maxSide = 2000, int $quality = 85): string
    {
        $img = str_starts_with((string) $file->getMimeType(), 'image/') ? self::load($file->getRealPath(), $maxSide) : null;
        if (! $img) {
            return $file->storeAs($dir, Str::random(40).'.'.strtolower($file->guessExtension() ?: 'bin'), $disk);
        }
        ob_start();
        imagejpeg($img, null, $quality);
        $data = ob_get_clean();
        imagedestroy($img);

        $path = trim($dir, '/').'/'.Str::random(40).'.jpg';
        Storage::disk($disk)->put($path, $data);

        return $path;
    }

    /** Convert an already-stored file (e.g. a Filament upload) to a smaller WebP in place. */
    public static function convertStored(string $path, string $disk = 'public', int $maxSide = 2000): string
    {
        if (! Storage::disk($disk)->exists($path)) {
            return $path;
        }
        // Already a WebP that is small enough: leave it.
        if (str_ends_with(strtolower($path), '.webp') && Storage::disk($disk)->size($path) < 600 * 1024) {
            return $path;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, Storage::disk($disk)->get($path));
        $new = self::storeWebp(new UploadedFile($tmp, basename($path), null, null, true), dirname($path), $maxSide);
        if ($new !== $path && Storage::disk($disk)->size($new) > 0) {
            Storage::disk($disk)->delete($path);
        }
        @unlink($tmp);

        return $new;
    }

    /** Opens a photo upright and no larger than $maxSide on its longest side; null when it is not a readable image. */
    public static function load(string $file, int $maxSide): ?\GdImage
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        $info = @getimagesize($file);
        if (! $info) {
            return null;
        }
        // A 48-megapixel phone photo needs ~200 MB while it is resized.
        if ($info[0] * $info[1] > 12_000_000) {
            @ini_set('memory_limit', '768M');
        } elseif ($info[0] * $info[1] > 4_000_000) {
            @ini_set('memory_limit', '512M');
        }
        $src = @imagecreatefromstring((string) file_get_contents($file));
        if (! $src) {
            return null;
        }
        $src = self::upright($src, $file, $info[2] ?? null);
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $maxSide / max($w, $h));
        if ($scale >= 1) {
            imagepalettetotruecolor($src);

            return $src;
        }
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);

        return $dst;
    }

    /** Phones save photos sideways with a "rotate me" note (EXIF); apply it so pictures are not turned. */
    private static function upright(\GdImage $img, string $file, ?int $type): \GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $img;
        }
        $o = (int) (@exif_read_data($file)['Orientation'] ?? 1);
        $rotated = match ($o) {
            3, 4 => imagerotate($img, 180, 0),
            5, 6 => imagerotate($img, -90, 0),
            7, 8 => imagerotate($img, 90, 0),
            default => $img,
        };
        if (in_array($o, [2, 4, 5, 7], true)) {
            imageflip($rotated, IMG_FLIP_HORIZONTAL);
        }
        if ($rotated !== $img) {
            imagedestroy($img);
        }

        return $rotated;
    }
}
