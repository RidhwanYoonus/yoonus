<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Photo touch-up for new product photos (only when the admin ticks it):
 *  - light: evens out brightness (gamma), adds a little contrast and sharpness;
 *  - square: puts the photo on an even square background, so all pieces line up in the shop;
 *  - background: a clean background colour behind the piece (needs a remove.bg key: the piece is cut out there).
 * The gold's own colour is never shifted.
 */
class PhotoStudio
{
    public const BACKGROUNDS = ['keep' => 'Keep the photo\'s own background', 'ffffff' => 'White', 'f7f3ec' => 'Ivory (warm white)', 'ececec' => 'Soft grey', '111111' => 'Black'];

    public static function removeBgKey(): string
    {
        return trim((string) Settings::get('removebg_api_key'));
    }

    /** @param array{light?: bool, square?: bool, background?: string} $o */
    public static function improve(string $path, array $o, string $disk = 'public'): void
    {
        $o += ['light' => false, 'square' => false, 'background' => 'keep'];
        $bg = preg_match('/^[0-9a-f]{6}$/', (string) $o['background']) ? $o['background'] : null;
        if (! $o['light'] && ! $o['square'] && ! $bg) {
            return;
        }
        $file = Storage::disk($disk)->path($path);
        if (! is_file($file)) {
            return;
        }
        // Background first (the cut-out works best on the original photo).
        if ($bg && self::removeBgKey() !== '') {
            rescue(fn () => self::replaceBackground($file, $bg), null, true);
        }
        $img = Images::load($file, 2000);
        if (! $img) {
            return;
        }
        if ($o['light']) {
            self::light($img);
        }
        if ($o['square'] || $bg) {
            $img = self::square($img, $bg);
        }
        imagejpeg($img, $file, 92);   // stored as WebP right after (Images::convertStored)
        imagedestroy($img);
    }

    /** Brightness to a pleasant middle (gamma from a small copy), a touch of contrast and sharpening. */
    public static function light(\GdImage $img): void
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $s = imagecreatetruecolor(64, 64);
        imagecopyresampled($s, $img, 0, 0, 0, 0, 64, 64, $w, $h);
        $sum = 0;
        $sq = 0;
        for ($x = 0; $x < 64; $x++) {
            for ($y = 0; $y < 64; $y++) {
                $c = imagecolorat($s, $x, $y);
                $l = (0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255)) / 255;
                $sum += $l;
                $sq += $l * $l;
            }
        }
        imagedestroy($s);
        $mean = max(0.05, min(0.95, $sum / 4096));
        $std = sqrt(max(0, $sq / 4096 - $mean * $mean));
        // Dark photo → brighter, very bright → a little darker; aim for a mid-tone of about 0.52.
        $gamma = log(0.52) / log($mean);
        $gamma = max(0.6, min(1.5, $gamma));
        if (abs($gamma - 1) > 0.04) {
            imagegammacorrect($img, 1.0, 1 / $gamma);
        }
        if ($std < 0.22) {
            imagefilter($img, IMG_FILTER_CONTRAST, $std < 0.15 ? -14 : -8);   // flat photo: more contrast
        }
        imageconvolution($img, [[0, -0.6, 0], [-0.6, 3.4, -0.6], [0, -0.6, 0]], 1, 0);   // gentle sharpening
    }

    /** The photo centred on a square of one colour (the photo's own edge colour when no colour is chosen). */
    public static function square(\GdImage $img, ?string $hex): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        [$r, $g, $b] = $hex ? sscanf($hex, '%02x%02x%02x') : self::edgeColour($img);
        $side = (int) round(max($w, $h) * ($hex ? 1.12 : 1.0));   // with a clean background, leave some air around the piece
        $out = imagecreatetruecolor($side, $side);
        imagefill($out, 0, 0, imagecolorallocate($out, $r, $g, $b));
        imagecopy($out, $img, intdiv($side - $w, 2), intdiv($side - $h, 2), 0, 0, $w, $h);
        imagedestroy($img);

        return $out;
    }

    /** Average colour of the photo's outer edge, to extend its background evenly. */
    private static function edgeColour(\GdImage $img): array
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $t = [0, 0, 0];
        $n = 0;
        for ($i = 0; $i < 40; $i++) {
            foreach ([[(int) ($w * $i / 40), 1], [(int) ($w * $i / 40), $h - 2], [1, (int) ($h * $i / 40)], [$w - 2, (int) ($h * $i / 40)]] as [$x, $y]) {
                $c = imagecolorat($img, max(0, min($w - 1, $x)), max(0, min($h - 1, $y)));
                $t[0] += ($c >> 16) & 255;
                $t[1] += ($c >> 8) & 255;
                $t[2] += $c & 255;
                $n++;
            }
        }

        return array_map(fn ($v) => (int) round($v / $n), $t);
    }

    /** remove.bg cuts the piece out and puts it on the colour; the file is replaced in place. */
    private static function replaceBackground(string $file, string $hex): void
    {
        $res = SecureHttp::send(fn ($http) => $http->timeout(60)->withHeaders(['X-Api-Key' => self::removeBgKey()])
            ->attach('image_file', (string) file_get_contents($file), 'photo.jpg')
            ->post('https://api.remove.bg/v1.0/removebg', ['size' => 'auto', 'bg_color' => $hex, 'format' => 'jpg', 'crop' => 'true', 'crop_margin' => '8%', 'type' => 'product']));
        if (! $res->successful()) {
            throw new RuntimeException('remove.bg: '.($res->json('errors.0.title') ?? 'HTTP '.$res->status()));
        }
        file_put_contents($file, $res->body());
    }
}
