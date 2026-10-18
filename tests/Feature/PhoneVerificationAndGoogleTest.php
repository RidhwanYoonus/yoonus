<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GoldCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PhoneVerificationAndGoogleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        Settings::set(['phone_verification' => true, 'id_verification' => 'off']);
        Product::create(['name' => 'Band', 'sku' => 'B-1', 'slug' => 'band', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 3]);
    }

    private function pickupForm(string $phone): array
    {
        $branch = Branch::firstOrCreate(['name' => 'Souk'], ['emirate' => 'Dubai', 'address' => 'Deira']);
        $form = ['email' => 'p@example.com', 'phone' => $phone, 'name' => 'P', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id, 'payment_method' => 'card'];
        $form['expected_total'] = $this->postJson('/checkout/quote', $form)->json('grand_total_raw');

        return $form;
    }

    public function test_checkout_needs_the_mobile_verified_by_code(): void
    {
        $this->post('/cart/add/band');
        $this->get('/checkout')->assertSee('Verify your mobile number')->assertSee('Send code on WhatsApp');

        $this->post('/checkout', $this->pickupForm('050 123 4567'))->assertSessionHasErrors('phone_verification');
        $this->assertSame(0, Order::count());

        $code = $this->postJson('/verify-phone/send', ['phone' => '050 123 4567'])->assertOk()->json('test_code');   // test mode: no WhatsApp yet
        $this->postJson('/verify-phone/check', ['phone' => '0501234567', 'code' => '000000'])->assertStatus(422);
        $this->postJson('/verify-phone/check', ['phone' => '+971 50 123 4567', 'code' => $code])->assertOk()->assertJson(['verified' => true, 'phone' => '+971501234567']);

        $this->post('/checkout', $this->pickupForm('+971501234567'))->assertSessionHasNoErrors();
        $this->assertSame(1, Order::count());

        // A different number is not covered by that verification.
        $this->post('/cart/add/band');
        $this->post('/checkout', $this->pickupForm('+971559999999'))->assertSessionHasErrors('phone_verification');
    }

    public function test_code_requests_are_limited_and_real_codes_are_not_shown_once_whatsapp_works(): void
    {
        foreach (range(1, 3) as $i) {
            $this->postJson('/verify-phone/send', ['phone' => '+971501112222'])->assertOk();
        }
        $this->postJson('/verify-phone/send', ['phone' => '+971501112222'])->assertStatus(422);

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'x']]])]);
        Settings::set(['whatsapp_provider' => 'meta', 'meta_wa_phone_number_id' => '1', 'meta_wa_token' => 't']);
        $this->postJson('/verify-phone/send', ['phone' => '+971503334444'])->assertOk()->assertJsonMissingPath('test_code');
        Http::assertSent(fn ($r) => str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE), 'verification code'));
    }

    public function test_signup_with_mobile_goes_to_verification_and_marks_account(): void
    {
        $this->post('/register', ['name' => 'Aisha', 'email' => 'aisha@example.com', 'phone' => '0507654321', 'password' => 'Secret123', 'password_confirmation' => 'Secret123'])
            ->assertRedirect(route('phone.verify'));
        $this->get('/account/verify-phone')->assertOk()->assertSee('Verify your mobile number');
        $code = $this->postJson('/verify-phone/send', ['phone' => '0507654321'])->json('test_code');
        $this->postJson('/verify-phone/check', ['phone' => '0507654321', 'code' => $code])->assertOk();

        $user = User::where('email', 'aisha@example.com')->first();
        $this->assertNotNull($user->phone_verified_at);
        $this->assertSame('+971507654321', $user->phone);
    }

    public function test_google_sign_in_creates_account_and_asks_for_mobile(): void
    {
        Settings::set(['google_client_id' => 'cid.apps.googleusercontent.com', 'google_client_secret' => 'sec']);
        $this->get('/login')->assertSee('Continue with Google');

        $redirect = $this->get('/auth/google')->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $redirect);
        parse_str(parse_url($redirect, PHP_URL_QUERY), $q);

        $payload = rtrim(strtr(base64_encode(json_encode(['aud' => 'cid.apps.googleusercontent.com', 'sub' => 'g-123', 'email' => 'Fatima@Gmail.com', 'email_verified' => true, 'name' => 'Fatima'])), '+/', '-_'), '=');
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['id_token' => 'h.'.$payload.'.s', 'access_token' => 'a'])]);

        $this->get('/auth/google/callback?code=abc&state=bad')->assertRedirect(route('login'));   // forged state
        $this->get('/auth/google')->assertRedirect();
        parse_str(parse_url($this->get('/auth/google')->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->get('/auth/google/callback?code=abc&state='.$q['state'])->assertRedirect(route('phone.verify'));

        $user = User::where('email', 'fatima@gmail.com')->first();
        $this->assertSame('g-123', $user->google_id);
        $this->assertAuthenticatedAs($user);
    }
}
