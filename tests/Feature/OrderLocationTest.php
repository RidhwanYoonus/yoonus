<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GoldCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\IpLocation;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderLocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        Settings::set(['id_verification' => 'off', 'handover_otp' => false]);
        Product::create(['name' => 'Ring', 'sku' => 'R-1', 'slug' => 'ring', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 3, 'is_active' => true]);
    }

    public function test_order_keeps_the_customers_real_ip_and_city_behind_cloudflare(): void
    {
        $cf = ['REMOTE_ADDR' => '104.23.225.60'];   // the request arrives from Cloudflare
        $headers = ['CF-Connecting-IP' => '94.200.11.22', 'CF-IPCountry' => 'AE', 'cf-ipcity' => 'Sharjah', 'cf-region' => 'Sharjah'];
        $branch = Branch::firstOrCreate(['name' => 'Souk'], ['emirate' => 'Dubai', 'address' => 'Deira']);
        $this->withServerVariables($cf)->withHeaders($headers)->post('/cart/add/ring');
        $form = ['email' => 'a@example.com', 'phone' => '+971501234567', 'name' => 'A', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id, 'payment_method' => 'card'];
        $form['expected_total'] = $this->withServerVariables($cf)->withHeaders($headers)->postJson('/checkout/quote', $form)->json('grand_total_raw');
        $this->withServerVariables($cf)->withHeaders($headers)->post('/checkout', $form);

        $order = Order::sole();
        $this->assertSame('94.200.11.22', $order->order_ip);
        $this->assertSame('Sharjah, United Arab Emirates', IpLocation::label($order));
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin/orders/'.$order->id)->assertSee('Ordered from')->assertSee('Sharjah, United Arab Emirates');
    }

    public function test_the_cloudflare_header_is_ignored_when_not_from_cloudflare(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '5.6.7.8'])->withHeaders(['CF-Connecting-IP' => '1.1.1.1'])->get('/');
        $this->assertSame('5.6.7.8', request()->ip());
    }

    public function test_free_lookup_finds_the_city_when_cloudflare_does_not_send_it(): void
    {
        Http::fake(['ipapi.co/*' => Http::response(['country_code' => 'IN', 'region' => 'Kerala', 'city' => 'Kochi'])]);
        $this->assertSame(['country' => 'IN', 'region' => 'Kerala', 'city' => 'Kochi'], IpLocation::lookup('49.37.1.2'));
        $this->assertSame([], IpLocation::lookup('192.168.1.10'));   // private address: nothing to look up
        Settings::set(['ip_lookup' => false]);
        $this->assertSame([], IpLocation::lookup('49.37.9.9'));
    }
}
