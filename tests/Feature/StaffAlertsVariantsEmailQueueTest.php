<?php

namespace Tests\Feature;

use App\Mail\ShopMessage;
use App\Messaging\EmailQueue;
use App\Messaging\Messenger;
use App\Models\GoldCategory;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\VideoCallRequest;
use App\Support\Settings;
use App\Support\TextStyle;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StaffAlertsVariantsEmailQueueTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        $this->product = Product::create([
            'name' => 'Band', 'sku' => 'B-1', 'slug' => 'band', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 3, 'is_active' => true,
        ]);
    }

    public function test_video_call_asks_for_a_platform_and_whatsapps_every_staff_number(): void
    {
        Settings::set(['video_call_staff_whatsapp' => "0501112233\n+971509998877, "]);
        $this->get('/product/band')->assertSee('Botim')->assertSee('Google Meet')->assertSee('Zoom')->assertSee('Microsoft Teams');

        $this->post('/product/band/video-call', ['name' => 'Aisha', 'phone' => '+971501112222', 'platform' => 'zoom'])->assertRedirect();
        $call = VideoCallRequest::sole();
        $this->assertSame('zoom', $call->platform);
        $sent = NotificationLog::where('template', 'video_call_staff')->where('channel', 'whatsapp')->get();
        $this->assertCount(2, $sent);
        $this->assertStringContainsString('Zoom video call', $sent->first()->body);

        // Botim: staff call the customer, so the page shows no join button.
        $this->post('/product/band/video-call', ['name' => 'Omar', 'phone' => '+971501113333', 'platform' => 'botim']);
        $botim = VideoCallRequest::latest('id')->first();
        $this->assertNull($botim->customerJoinUrl());
        $this->get(route('video-call.show', $botim))->assertOk()->assertSee('Botim');
        $this->post('/product/band/video-call', ['name' => 'X', 'phone' => '+971501113334', 'platform' => 'skype'])->assertSessionHasErrors('platform');
    }

    public function test_new_cod_order_is_sent_to_staff_numbers_and_emails(): void
    {
        Settings::set(['staff_order_alerts' => true, 'staff_order_whatsapp' => '0501112233', 'staff_order_emails' => 'sales@shop.ae', 'handover_otp' => false]);
        $this->product->update(['sizes' => ['14', '16'], 'gold_colours' => ['rose']]);
        $this->post('/cart/add/band', ['size' => '16', 'colour' => 'rose']);
        $this->post('/checkout', [
            'email' => 'guest@example.com', 'phone' => '+971501234567', 'name' => 'Guest', 'fulfilment' => 'delivery',
            'zone_id' => ShippingZone::where('code', 'other_emirates')->value('id'), 'line1' => 'Tower 1', 'city' => 'Al Reem',
            'payment_method' => 'cod', 'expected_total' => '1191.75',
        ] + $this->idFields())->assertSessionHasNoErrors();
        $order = Order::sole();

        $wa = NotificationLog::where('template', 'staff_new_order')->where('channel', 'whatsapp')->sole();
        $this->assertStringContainsString($order->number, $wa->body);
        $this->assertStringContainsString('Size 16 · Rose gold', $wa->body);
        Mail::assertSent(ShopMessage::class, fn ($m) => $m->hasTo('sales@shop.ae') && str_contains($m->subjectLine, $order->number));
        $this->assertSame(['size' => '16', 'colour' => 'rose'], $order->items->first()->options);
    }

    public function test_staff_alerts_stay_quiet_when_switched_off(): void
    {
        Settings::set(['staff_order_alerts' => false, 'staff_order_whatsapp' => '0501112233']);
        \App\Support\StaffAlerts::newOrder(new Order(['number' => 'JW-1']), 'COD');
        $this->assertSame(0, NotificationLog::where('template', 'staff_new_order')->count());
    }

    public function test_sizes_and_colours_must_be_chosen_and_show_in_bag_and_invoice_lines(): void
    {
        $this->product->update(['sizes' => ['14', '16'], 'gold_colours' => ['yellow', 'white']]);
        $this->get('/product/band')->assertSee('name="size"', false)->assertSee('name="colour"', false)->assertSee('White gold');
        $this->get('/')->assertOk();

        $this->post('/cart/add/band')->assertRedirect(route('product.show', $this->product))->assertSessionHasErrors('cart');
        $this->post('/cart/add/band', ['size' => '15', 'colour' => 'white'])->assertSessionHasErrors('cart');
        $this->post('/cart/add/band', ['size' => '14', 'colour' => 'white'])->assertRedirect(route('cart.show'));
        $this->get('/cart')->assertSee('Size 14 · White gold');
        $this->get('/checkout')->assertSee('Size 14', false);

        // A piece without choices adds straight away.
        $this->product->update(['sizes' => [], 'gold_colours' => []]);
        $this->post('/cart/add/band')->assertRedirect(route('cart.show'));
    }

    public function test_product_names_and_descriptions_get_neat_capitals(): void
    {
        $this->assertSame('Gold Ring with Diamond 18K', TextStyle::name('GOLD RING WITH diamond 18k'));
        $this->assertSame('Ladies Bracelet of 22K Gold (GIA Certified)', TextStyle::name('ladies bracelet OF 22k gold (gia certified)'));
        $this->assertSame('Two-Tone Chain 3.5mm', TextStyle::name('two-tone chain 3.5MM'));
        $this->assertSame("Beautiful ring. It has a VVS1 diamond, 0.5ct.\nMade in UAE!", TextStyle::description("BEAUTIFUL RING . IT HAS A VVS1 DIAMOND,0.5CT.\nMADE IN UAE !"));

        $this->product->update(['name' => 'rose gold BANGLE', 'description' => 'light and elegant.  perfect for daily wear']);
        $this->assertSame('Rose Gold Bangle', $this->product->fresh()->name);
        $this->assertSame('Light and elegant. Perfect for daily wear', $this->product->fresh()->description);

        Settings::set(['product_text_style' => false]);
        $this->product->update(['name' => 'as TYPED']);
        $this->assertSame('as TYPED', $this->product->fresh()->name);
    }

    public function test_daily_email_limit_holds_ordinary_emails_but_never_order_or_code_emails(): void
    {
        Settings::set(['email_daily_limit' => 2]);
        $m = app(Messenger::class);
        $mail = fn ($s) => new ShopMessage($s, ['Hello']);
        $m->email('a@x.ae', $mail('Offer 1'), 'gold_drop');
        $m->email('b@x.ae', $mail('Offer 2'), 'gold_drop');
        $m->email('c@x.ae', $mail('Offer 3'), 'gold_drop');            // over the limit: waits
        $m->email('d@x.ae', $mail('Your order'), 'order_paid');         // invoice email: always sent
        $m->email('e@x.ae', $mail('Code'), 'phone_verification');       // code: always sent

        Mail::assertSent(ShopMessage::class, 4);
        Mail::assertNotSent(ShopMessage::class, fn ($x) => $x->hasTo('c@x.ae'));
        $this->assertSame(1, EmailQueue::waiting());
        $this->assertSame('queued', NotificationLog::where('recipient', 'c@x.ae')->value('status'));

        // Still today: nothing more goes out. Next day: the waiting email is sent.
        $this->assertSame(0, EmailQueue::flush());
        $this->travel(1)->days();
        $this->assertSame(1, EmailQueue::flush());
        Mail::assertSent(ShopMessage::class, fn ($x) => $x->hasTo('c@x.ae') && $x->subjectLine === 'Offer 3');
        $this->assertSame(0, EmailQueue::waiting());
        $this->assertNotNull(DB::table('queued_emails')->value('sent_at'));

        // 0 = no limit.
        Settings::set(['email_daily_limit' => 0]);
        $m->email('f@x.ae', $mail('Offer 4'), 'gold_drop');
        Mail::assertSent(ShopMessage::class, fn ($x) => $x->hasTo('f@x.ae'));
    }
}
