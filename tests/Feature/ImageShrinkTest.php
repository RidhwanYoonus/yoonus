<?php

namespace Tests\Feature;

use App\Support\IdDocument;
use App\Support\Images;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageShrinkTest extends TestCase
{
    /** A noisy 4000 × 3000 photo, like a phone camera picture. */
    private function phonePhoto(int $w = 4000, int $h = 3000): UploadedFile
    {
        $img = imagecreatetruecolor($w, $h);
        for ($i = 0; $i < 4000; $i++) {
            imagefilledellipse($img, random_int(0, $w), random_int(0, $h), random_int(20, 200), random_int(20, 200), imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        }
        $path = tempnam(sys_get_temp_dir(), 'ph').'.jpg';
        imagejpeg($img, $path, 98);
        imagedestroy($img);

        return new UploadedFile($path, 'IMG_2041.jpg', 'image/jpeg', null, true);
    }

    public function test_id_photo_is_shrunk_to_2000px_jpeg(): void
    {
        Storage::fake('local');
        $file = $this->phonePhoto();
        $before = $file->getSize();

        $path = IdDocument::store($file);

        $this->assertStringEndsWith('.jpg', $path);
        [$w, $h] = getimagesizefromstring(Storage::disk('local')->get($path));
        $this->assertSame([2000, 1500], [$w, $h]);
        $this->assertLessThan($before / 2, Storage::disk('local')->size($path));
    }

    public function test_tall_photo_is_limited_on_its_longest_side(): void
    {
        Storage::fake('public');
        $path = Images::storeWebp($this->phonePhoto(1500, 4000), 'products');
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([750, 2000], [$w, $h]);
    }

    public function test_pdf_id_is_kept_as_it_is(): void
    {
        Storage::fake('local');
        $pdf = UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        $path = IdDocument::store($pdf);
        $this->assertStringEndsWith('.pdf', $path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($path));
    }

    public function test_large_webp_from_admin_is_recompressed(): void
    {
        Storage::fake('public');
        $img = imagecreatetruecolor(3500, 3500);
        for ($i = 0; $i < 3000; $i++) {
            imagefilledellipse($img, random_int(0, 3500), random_int(0, 3500), 80, 80, imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        }
        ob_start();
        imagewebp($img, null, 100);
        Storage::disk('public')->put('products/big.webp', ob_get_clean());

        $new = Images::convertStored('products/big.webp');
        [$w] = getimagesizefromstring(Storage::disk('public')->get($new));
        $this->assertSame(2000, $w);
        Storage::disk('public')->assertMissing('products/big.webp');
    }
}
