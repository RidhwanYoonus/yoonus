<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicFileFallbackTest extends TestCase
{
    public function test_photos_are_served_when_the_public_storage_link_is_missing(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('ring.jpg', 40, 40)->store('products', 'public');

        $this->get('/storage/'.$path)->assertOk()->assertHeader('Cache-Control', 'immutable, max-age=2592000, public');
        $this->get('/storage/products/missing.jpg')->assertNotFound();
        $this->get('/storage/../.env')->assertNotFound();
        $this->get('/storage/..%2F..%2F.env')->assertNotFound();
    }

    public function test_converted_photo_is_never_empty(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('phone.jpg', 3000, 2000)->store('products', 'public');

        $new = \App\Support\Images::convertStored($path);

        $this->assertGreaterThan(100, Storage::disk('public')->size($new));
        $this->assertNotFalse(getimagesizefromstring(Storage::disk('public')->get($new)));
    }
}
