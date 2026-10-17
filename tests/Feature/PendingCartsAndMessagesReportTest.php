<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\GoldCategory;
use App\Models\NotificationLog;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PendingCartsAndMessagesReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_carts_report_lists_unordered_bags_with_contact_and_value(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $p = Product::create(['name' => 'Kada Bangle', 'sku' => 'K-1', 'slug' => 'kada', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 3]);
        $cart = fn (array $a) => Cart::create($a + ['token' => (string) Str::uuid(), 'items' => [['product_id' => $p->id, 'qty' => 2]], 'last_known_total' => 2200, 'last_activity_at' => now()]);
        $cart(['email' => 'waiting@example.com', 'phone' => '+971501112222', 'checkout_started_at' => now()]);
        $cart(['email' => 'bought@example.com', 'converted_at' => now()]);   // became an order
        $cart(['items' => [], 'last_known_total' => 0]);                     // empty bag

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/pending-carts')->assertOk()
            ->assertSee('waiting@example.com')->assertSee('Kada Bangle × 2')->assertSee('Reached checkout')
            ->assertDontSee('bought@example.com')->assertSee('Pending carts · 30 days');
    }

    public function test_messages_report_counts_whatsapp_and_email(): void
    {
        NotificationLog::create(['channel' => 'whatsapp', 'recipient' => '+971501112222', 'template' => 'handover_otp', 'status' => 'sent', 'body' => 'Your code']);
        NotificationLog::create(['channel' => 'email', 'recipient' => 'a@example.com', 'template' => 'order_paid', 'status' => 'failed', 'error' => 'SMTP down']);

        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin/messages-sent')->assertOk()
            ->assertSee('WhatsApp sent · this month')->assertSee('Handover code')->assertSee('+971501112222')->assertSee('Failed');
    }
}
