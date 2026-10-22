<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(ReferenceDataSeeder::class);
    }

    public function test_without_branding_the_shop_name_and_default_icon_are_shown(): void
    {
        $this->get('/')->assertOk()->assertSee('favicon.ico')->assertDontSee('Visit our showroom');
    }

    public function test_logo_favicon_home_photo_and_showroom_are_used(): void
    {
        foreach (['brand_logo' => 'logo.png', 'brand_favicon' => 'icon.png', 'home_hero_image' => 'hero.jpg', 'showroom_image' => 'showroom.jpg'] as $key => $name) {
            Settings::set([$key => UploadedFile::fake()->image($name, 400, 400)->store('branding', 'public')]);
        }
        Settings::set(['store_address' => 'Deira City Centre, Dubai']);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString(Settings::imageUrl('brand_logo'), $html);
        $this->assertStringContainsString('rel="icon" href="'.Settings::imageUrl('brand_favicon').'"', $html);
        $this->assertStringContainsString(Settings::imageUrl('home_hero_image'), $html);
        $this->assertStringContainsString(Settings::imageUrl('showroom_image'), $html);
        $this->assertStringContainsString('Visit our showroom', $html);
        $this->assertStringContainsString('google.com/maps/search', $html);
    }

    public function test_admin_has_a_branding_tab(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('Branding')->assertSee('Showroom photo');
        Livewire::test(\App\Filament\Pages\Settings::class)->set('data.showroom_title', 'Our Deira showroom')->call('save')->assertHasNoErrors();
        $this->assertSame('Our Deira showroom', Settings::get('showroom_title'));
    }
}
