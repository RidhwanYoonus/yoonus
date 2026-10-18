<?php

namespace Tests\Feature;

use App\Messaging\WhatsApp;
use App\Models\NotificationLog;
use App\Models\User;
use App\Support\MailSettings;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MessagingSetupTest extends TestCase
{
    use RefreshDatabase;

    private function meta(array $over = []): void
    {
        Settings::set($over + ['whatsapp_provider' => 'meta', 'meta_wa_phone_number_id' => '1098765', 'meta_wa_token' => 'EAAtest', 'meta_wa_template' => 'store_update']);
    }

    public function test_without_setup_messages_are_logged_not_sent(): void
    {
        Http::fake();
        $this->assertFalse(WhatsApp::configured());
        app(WhatsApp::class)->send('0501234567', 'Hello', 'test');
        Http::assertNothingSent();
        $this->assertSame('logged', NotificationLog::sole()->status);
    }

    public function test_meta_sends_template_with_flattened_text_to_uae_number(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        $this->meta();
        $this->assertTrue(app(WhatsApp::class)->send('050 123 4567', "Order JW-1 is ready\nCollect from Deira", 'order_ready'));

        Http::assertSent(fn (Request $r) => $r->url() === 'https://graph.facebook.com/v21.0/1098765/messages'
            && $r->hasHeader('Authorization', 'Bearer EAAtest')
            && $r['to'] === '971501234567'
            && $r['template']['name'] === 'store_update'
            && $r['template']['components'][0]['parameters'][0]['text'] === 'Order JW-1 is ready · Collect from Deira');
        $this->assertSame('sent', NotificationLog::sole()->status);
    }

    public function test_meta_uploads_invoice_pdf_and_sends_document_template(): void
    {
        Http::fake([
            'graph.facebook.com/v21.0/1098765/media' => Http::response(['id' => 'media-42']),
            'graph.facebook.com/v21.0/1098765/messages' => Http::response(['messages' => [['id' => 'wamid.2']]]),
        ]);
        $this->meta(['meta_wa_invoice_template' => 'order_invoice']);
        app(WhatsApp::class)->sendDocument('+971501234567', '%PDF-1.4 test', 'INV-2026-000001.pdf', 'Payment received', 'order_paid');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/media'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/messages')
            && $r['template']['name'] === 'order_invoice'
            && $r['template']['components'][0]['parameters'][0]['document'] === ['id' => 'media-42', 'filename' => 'INV-2026-000001.pdf']);
    }

    public function test_meta_error_is_recorded_with_reason(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 401)]);
        $this->meta();
        $this->assertFalse(app(WhatsApp::class)->send('+971501234567', 'Hi', 'test'));
        $log = NotificationLog::sole();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('Invalid OAuth', $log->error);
    }

    public function test_smtp_settings_from_admin_are_applied(): void
    {
        Settings::set(['mail_mailer' => 'smtp', 'mail_host' => 'smtp.gmail.com', 'mail_port' => 587, 'mail_encryption' => 'tls',
            'mail_username' => 'orders@shop.ae', 'mail_password' => 'app-pass', 'mail_from_address' => 'orders@shop.ae', 'mail_from_name' => 'My Shop']);
        MailSettings::apply();
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.gmail.com', config('mail.mailers.smtp.host'));
        $this->assertSame('orders@shop.ae', config('mail.from.address'));
        $this->assertSame('My Shop', config('mail.from.name'));

        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin/settings?tab=-email-tab')->assertOk()
            ->assertSee('Send test email')->assertSee('Send test WhatsApp');
    }
}
