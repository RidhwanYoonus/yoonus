<?php

namespace Tests\Feature;

use App\Mail\ShopMessage;
use App\Models\Branch;
use App\Models\GoldCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Support\AdminTwoFactor;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SecurityAndPoliciesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_policy_pages_render_in_both_languages_and_are_linked(): void
    {
        foreach (['terms-and-conditions', 'refund-policy', 'privacy-policy', 'delivery-policy'] as $slug) {
            $this->get("/{$slug}?lang=en")->assertOk();
            $this->get("/{$slug}?lang=ar")->assertOk();
        }
        $this->get('/refund-policy?lang=en')->assertSee('All sales are final')->assertSee((string) Settings::get('store_legal_name'));
        $this->get('/refund-policy?lang=ar')->assertSee('جميع المبيعات نهائية');
        $this->get('/?lang=en')->assertSee(route('policy.show', 'privacy-policy'), false);
        $this->get('/sitemap.xml')->assertSee('/refund-policy', false);
        $this->get('/not-a-policy')->assertNotFound();
    }

    public function test_admin_written_policy_replaces_default_and_is_sanitised(): void
    {
        Settings::set(['policy_terms_en' => '<h2>Our rules</h2><p onclick="steal()">Be nice</p><script>alert(1)</script><a href="javascript:alert(1)">x</a>']);
        $html = $this->get('/terms-and-conditions?lang=en')->assertOk()->assertSee('Our rules')->getContent();
        $this->assertStringNotContainsString('alert(1)</script>', $html);
        $this->assertStringNotContainsString('steal()', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);
        $this->get('/terms-and-conditions?lang=ar')->assertSee('من نحن');   // Arabic still uses the default
    }

    public function test_checkout_requires_agreeing_to_terms_and_records_it(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        Settings::set(['id_verification' => 'off', 'require_terms_acceptance' => true]);
        Product::create(['name' => 'Band', 'sku' => 'B-1', 'slug' => 'band', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 3]);
        $branch = Branch::firstOrCreate(['name' => 'Souk'], ['emirate' => 'Dubai', 'address' => 'Deira']);
        $this->post('/cart/add/band');
        $form = ['email' => 'm@example.com', 'phone' => '+971501234567', 'name' => 'Mariam', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id, 'payment_method' => 'card'];
        $form['expected_total'] = $this->postJson('/checkout/quote', $form)->json('grand_total_raw');

        $this->get('/checkout')->assertSee('name="accept_terms"', false);
        $this->post('/checkout', $form)->assertSessionHasErrors('accept_terms');
        $this->assertSame(0, Order::count());

        $this->post('/checkout', $form + ['accept_terms' => '1'])->assertRedirect();
        $order = Order::sole();
        $this->assertNotNull($order->terms_accepted_at);
        $this->assertSame('127.0.0.1', $order->terms_accepted_ip);
    }

    public function test_security_headers_and_no_caching_of_private_pages(): void
    {
        $res = $this->get('/');
        $res->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("frame-ancestors 'self'", $res->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', $this->get('/cart')->headers->get('Cache-Control'));
    }

    public function test_admin_two_step_sign_in_with_email_code(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email' => 'boss@shop.ae']);
        $this->actingAs($admin)->get('/admin')->assertOk();   // off by default

        Settings::set(['admin_two_factor' => true]);
        $this->actingAs($admin)->get('/admin')->assertRedirect(route('admin.two-factor'));
        $this->get('/admin-verify')->assertOk()->assertSee('boss@shop.ae');

        $code = null;
        Mail::assertSent(ShopMessage::class, function (ShopMessage $m) use (&$code) {
            preg_match('/(\d{6})/', $m->subjectLine, $x);
            $code = $x[1] ?? null;

            return $m->hasTo('boss@shop.ae');
        });
        $this->post('/admin-verify', ['code' => '000000' === $code ? '111111' : '000000'])->assertSessionHasErrors('code');
        $this->get('/admin')->assertRedirect(route('admin.two-factor'));
        $this->get('/admin/id-documents/order/1/front')->assertRedirect(route('admin.two-factor'));   // ID photos need the code too

        $this->post('/admin-verify', ['code' => $code, 'trust' => '1'])->assertRedirect(url('/admin/id-documents/order/1/front'))->assertCookie(AdminTwoFactor::COOKIE);
        $this->get('/admin')->assertOk();
        $this->assertTrue(SecurityEvent::where('event', 'two_factor_passed')->exists());

        $this->artisan('shop:two-factor off')->assertSuccessful();
        $this->assertFalse(Settings::bool('admin_two_factor'));
    }

    public function test_admin_is_signed_out_after_inactivity(): void
    {
        Settings::set(['admin_idle_minutes' => 15]);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->withSession(['admin.last_seen' => time() - 16 * 60])->get('/admin')->assertRedirect(route('filament.admin.auth.login'));
        $this->assertGuest();
        $this->assertTrue(SecurityEvent::where('event', 'idle_logout')->exists());
    }

    public function test_failed_logins_are_logged_and_blocked(): void
    {
        User::factory()->create(['email' => 'a@shop.ae']);
        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => 'a@shop.ae', 'password' => 'wrong-'.$i]);
        }
        $this->assertSame(5, SecurityEvent::where('event', 'login_failed')->count());
        $this->post('/login', ['email' => 'a@shop.ae', 'password' => 'again'])->assertSessionHasErrors('email');
        $this->assertTrue(SecurityEvent::where('event', 'lockout')->exists());
    }

    public function test_security_log_is_admin_only(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin/security-log')->assertOk();
        $staff = User::factory()->create(['is_staff' => true]);
        $staff->forceFill(['permissions' => ['orders' => ['view' => true]]])->save();
        $this->actingAs($staff)->get('/admin/security-log')->assertForbidden();
    }
}
