<?php

namespace Tests\Feature;

use App\Models\GoldCategory;
use App\Models\NotificationLog;
use App\Models\Product;
use App\Models\VideoCallRequest;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductPageExtrasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['18K' => '300.00']);
        Product::create(['name' => 'Solitaire', 'sku' => 'D-1', 'slug' => 'solitaire', 'gold_category_id' => GoldCategory::where('name', '18K')->value('id'),
            'weight_grams' => 3, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stone_value' => 2400, 'stone_type' => 'Diamond',
            'stock_qty' => 1, 'is_active' => true]);
    }

    public function test_share_opens_any_app_with_a_menu_fallback(): void
    {
        $this->get('/product/solitaire')->assertSee('navigator.share', false)->assertSee('facebook.com/sharer', false)
            ->assertSee('t.me/share', false)->assertSee('Copy link');
    }

    public function test_diamond_is_shown_on_the_large_photo_even_without_carat_details(): void
    {
        $this->get('/product/solitaire')->assertSee('bg-onyx/70 px-4 py-2.5', false);   // the diamond badge on the photo
        $this->assertTrue(Product::first()->hasDiamond());
    }

    public function test_store_visit_button_can_be_hidden(): void
    {
        $this->get('/product/solitaire')->assertSee('Book a store visit');
        Settings::set(['store_visit_button' => false]);
        $this->get('/product/solitaire')->assertDontSee('Book a store visit');
    }

    public function test_gold_prices_move_like_a_ticker_on_phones(): void
    {
        $this->get('/')->assertSee('rate-track', false)->assertSee('@keyframes rate-ticker', false);
    }

    public function test_google_meet_link_is_used_as_it_is_and_jitsi_gets_a_room(): void
    {
        $call = new VideoCallRequest(['room' => 'almasroor-abc123']);
        $this->assertSame('https://meet.jit.si/almasroor-abc123', $call->roomUrl());

        Settings::set(['video_call_base_url' => 'https://meet.google.com/csg-wfsx-ihn']);
        $this->assertSame('https://meet.google.com/csg-wfsx-ihn', $call->roomUrl());
        Settings::set(['video_call_base_url' => 'https://us05web.zoom.us/j/123456789?pwd=x']);
        $this->assertSame('https://us05web.zoom.us/j/123456789?pwd=x', $call->roomUrl());
    }

    public function test_customer_can_get_the_verification_code_by_sms(): void
    {
        Settings::set(['phone_verification' => true]);
        $this->get('/register')->assertDontSee('value="sms"', false);   // SMS not set up yet

        Settings::set(['sms_provider' => 'http', 'sms_http_url' => 'https://sms.example.ae/send?to={to}&text={message}']);
        Http::fake(['sms.example.ae/*' => Http::response('OK')]);
        $this->get('/register')->assertSee('value="sms"', false)->assertSee('Get the code by:');

        $this->postJson('/verify-phone/send', ['phone' => '0501112233', 'channel' => 'sms'])->assertOk()->assertJsonPath('message', 'Code sent by SMS — it is valid for 10 minutes.')
            ->assertJsonMissingPath('test_code');
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://sms.example.ae/send?to=971501112233&text='));
        $this->assertSame('sms', NotificationLog::latest('id')->value('channel'));
    }

    public function test_customer_can_get_the_verification_code_by_email(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        Settings::set(['phone_verification' => true]);
        $this->get('/register')->assertSee('value="email"', false);

        $this->postJson('/verify-phone/send', ['phone' => '0501112233', 'channel' => 'email'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson('/verify-phone/send', ['phone' => '0501112233', 'channel' => 'email', 'email' => 'nora@example.com'])->assertOk()
            ->assertJsonPath('message', 'Code sent to your email — it is valid for 10 minutes. Check the spam folder too.');
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\ShopMessage::class, fn ($m) => $m->hasTo('nora@example.com'));

        Settings::set(['phone_verify_email' => false]);
        $this->get('/register')->assertDontSee('value="email"', false);
    }
}
