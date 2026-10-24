<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Mail\ShopMessage;
use App\Models\Branch;
use App\Models\GoldCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\PhotoStudio;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StaySignedInPhotoTouchUpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        Settings::set(['id_verification' => 'off', 'handover_otp' => false]);
        Product::create(['name' => 'Band', 'sku' => 'B-1', 'slug' => 'band', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 5, 'is_active' => true]);
    }

    private function guestOrder(string $email, array $extra = [])
    {
        $branch = Branch::firstOrCreate(['name' => 'Souk'], ['emirate' => 'Dubai', 'address' => 'Deira']);
        $this->post('/cart/add/band');
        $form = ['email' => $email, 'phone' => '+971501234567', 'name' => 'Sara', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id, 'payment_method' => 'card'] + $extra;
        $form['expected_total'] = $this->postJson('/checkout/quote', $form)->json('grand_total_raw');

        return $this->post('/checkout', $form);
    }

    public function test_guest_who_keeps_signed_in_gets_an_account_and_stays_signed_in(): void
    {
        $this->post('/cart/add/band');
        $this->get('/checkout')->assertSee('Keep me signed in on this phone');
        $this->guestOrder('sara@example.com', ['keep_signed_in' => '1']);

        $user = User::where('email', 'sara@example.com')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame($user->id, Order::sole()->user_id);
        $this->assertNotNull($user->fresh()->remember_token);   // stays signed in after the visit ends
        Mail::assertSent(ShopMessage::class, fn ($m) => $m->hasTo('sara@example.com') && $m->subjectLine === 'Your account is ready');
    }

    public function test_an_existing_email_is_never_signed_in_from_checkout(): void
    {
        User::factory()->create(['email' => 'owner@example.com']);
        $this->guestOrder('owner@example.com', ['keep_signed_in' => '1']);
        $this->assertGuest();
        $this->assertNull(Order::sole()->user_id);
    }

    public function test_untick_or_setting_off_keeps_the_old_guest_checkout(): void
    {
        $this->guestOrder('sara@example.com');
        $this->assertGuest();
        $this->assertSame(0, User::where('email', 'sara@example.com')->count());
    }

    public function test_sign_up_and_login_remember_the_phone_and_password_can_be_reset(): void
    {
        $this->post('/register', ['name' => 'Anna', 'email' => 'anna@example.com', 'password' => 'Secret123', 'password_confirmation' => 'Secret123']);
        $this->assertNotNull(User::where('email', 'anna@example.com')->value('remember_token'));
        Auth::logout();

        $this->get('/login')->assertSee('name="remember" value="1" checked', false)->assertSee('Forgot password?');
        $this->post('/forgot-password', ['email' => 'anna@example.com'])->assertSessionHas('status');
        $url = null;
        Mail::assertSent(ShopMessage::class, function ($m) use (&$url) {
            $url = $m->actionUrl;

            return $m->hasTo('anna@example.com');
        });
        $this->get($url)->assertOk()->assertSee('Choose your password');
        $token = basename(parse_url($url, PHP_URL_PATH));
        $this->post('/reset-password', ['token' => $token, 'email' => 'anna@example.com', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertRedirect(route('account'));
        $this->assertTrue(Auth::attempt(['email' => 'anna@example.com', 'password' => 'NewPass123']));
    }

    private function photo(int $w, int $h, int $grey): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, $grey, $grey, $grey));
        imagefilledrectangle($img, (int) ($w / 3), (int) ($h / 3), (int) ($w / 2), (int) ($h / 2), imagecolorallocate($img, $grey + 30, $grey + 20, 10));
        ob_start();
        imagejpeg($img);
        $path = 'products/test-'.uniqid().'.jpg';
        Storage::disk('public')->put($path, ob_get_clean());

        return $path;
    }

    private function brightness(string $path): float
    {
        $img = imagecreatefromstring(Storage::disk('public')->get($path));
        $c = imagecolorat($img, 2, 2);

        return (($c >> 16) & 255) / 255;
    }

    public function test_touch_up_brightens_a_dark_photo_and_makes_it_square(): void
    {
        Storage::fake('public');
        $path = $this->photo(400, 300, 50);
        PhotoStudio::improve($path, ['light' => true, 'square' => true]);
        $img = imagecreatefromstring(Storage::disk('public')->get($path));
        $this->assertSame(imagesx($img), imagesy($img));
        $this->assertGreaterThan(0.25, $this->brightness($path));   // was 50/255 ≈ 0.2
    }

    public function test_product_form_touches_up_only_the_new_photos(): void
    {
        Storage::fake('public');
        $old = $this->photo(400, 300, 50);
        $product = Product::sole();
        $product->update(['images' => [$old]]);
        $new = $this->photo(300, 200, 50);

        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSee('Photo touch-up')
            ->set('data.images', [$old, $new])->set('data.touch_light', true)->set('data.touch_square', true)
            ->call('save')->assertHasNoErrors();

        $saved = $product->fresh()->images;
        $this->assertCount(2, $saved);
        $first = imagecreatefromstring(Storage::disk('public')->get($saved[0]));
        $this->assertSame([400, 300], [imagesx($first), imagesy($first)]);   // the earlier photo is not touched
        $img = imagecreatefromstring(Storage::disk('public')->get($saved[1]));
        $this->assertSame(imagesx($img), imagesy($img));
    }

    public function test_photo_studio_saves_the_white_photo_keeps_the_original_and_can_put_it_back(): void
    {
        Storage::fake('public');
        $orig = $this->photo(400, 300, 90);
        $product = Product::sole();
        $product->update(['images' => [$orig]]);

        $this->actingAs(User::factory()->create())->get(route('admin.photo-studio', $product))->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get(route('admin.photo-studio', $product))->assertOk()->assertSee('Royal blue velvet')->assertSee('Make it beautiful')->assertSee('Compare')->assertSee('BiRefNet_lite-ONNX', false)->assertSee($orig, false);

        $white = \Illuminate\Http\UploadedFile::fake()->image('studio.jpg', 1600, 1600);
        $new = $this->post(route('admin.photo-studio.save', $product), ['index' => 0, 'path' => $orig, 'file' => $white])->assertOk()->json('path');
        $product->refresh();
        $this->assertSame([$new], $product->images);
        $this->assertSame([$new => $orig], $product->photo_originals);
        Storage::disk('public')->assertExists($orig);   // the original is kept

        // Stale page (photos changed meanwhile) is refused.
        $this->post(route('admin.photo-studio.save', $product), ['index' => 0, 'path' => $orig, 'file' => $white])->assertStatus(409);

        // Opens inside the product page (modal), and the form picks up the saved photo.
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->mountAction('studio')->assertSee('embed=1', false)
            ->call('studioSaved')->assertSet('data.images', fn ($v) => in_array($new, array_values((array) $v), true));

        $this->postJson(route('admin.photo-studio.restore', $product), ['index' => 0, 'path' => $new])->assertOk();
        $product->refresh();
        $this->assertSame([$orig], $product->images);
        $this->assertNull($product->photo_originals);
        Storage::disk('public')->assertMissing($new);
    }

    public function test_store_photos_can_be_added_as_studio_backgrounds_and_removed(): void
    {
        Storage::fake('public');
        $product = Product::sole();
        $this->actingAs(User::factory()->create())->post(route('admin.photo-studio.bg.add'), ['file' => \Illuminate\Http\UploadedFile::fake()->image('shop.jpg', 800, 600)])->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $path = $this->post(route('admin.photo-studio.bg.add'), ['file' => \Illuminate\Http\UploadedFile::fake()->image('shop.jpg', 800, 600)])->assertOk()->json('path');
        Storage::disk('public')->assertExists($path);
        $this->get(route('admin.photo-studio', $product))->assertSee($path, false);

        $this->postJson(route('admin.photo-studio.bg.remove'), ['path' => $path])->assertOk();
        Storage::disk('public')->assertMissing($path);
        $this->assertSame([], Settings::get('studio_backgrounds'));
        $this->postJson(route('admin.photo-studio.bg.remove'), ['path' => 'products/someone-else.jpg'])->assertOk();   // only its own list is touched
    }
}
