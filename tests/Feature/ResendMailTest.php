<?php

namespace Tests\Feature;

use App\Mail\ResendTransport;
use App\Mail\ShopMessage;
use App\Support\MailSettings;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ResendMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_goes_through_the_resend_api_with_the_saved_key(): void
    {
        Http::fake([ResendTransport::ENDPOINT => Http::response(['id' => 'abc-123'])]);
        Settings::set(['mail_mailer' => 'resend', 'resend_api_key' => 're_test_key', 'mail_from_address' => 'orders@shop.ae', 'mail_from_name' => 'Shop']);
        MailSettings::apply();
        $this->assertSame('resend_api', config('mail.default'));

        Mail::to('buyer@example.com')->send((new ShopMessage('Your invoice', ['Thank you']))->attachData('%PDF-1.4', 'invoice.pdf', ['mime' => 'application/pdf']));

        Http::assertSent(function (Request $r) {
            return $r->url() === ResendTransport::ENDPOINT && $r->hasHeader('Authorization', 'Bearer re_test_key')
                && $r['to'] === ['buyer@example.com'] && $r['subject'] === 'Your invoice' && str_contains($r['from'], 'orders@shop.ae')
                && $r['attachments'][0]['filename'] === 'invoice.pdf' && base64_decode($r['attachments'][0]['content']) === '%PDF-1.4'
                && str_contains($r['html'], 'Thank you');
        });
    }

    public function test_a_refused_email_raises_the_resend_reason(): void
    {
        Http::fake([ResendTransport::ENDPOINT => Http::response(['message' => 'The shop.ae domain is not verified'], 403)]);
        Settings::set(['mail_mailer' => 'resend', 'resend_api_key' => 're_x', 'mail_from_address' => 'orders@shop.ae']);
        MailSettings::apply();

        $this->expectExceptionMessage('domain is not verified');
        Mail::to('buyer@example.com')->send(new ShopMessage('Hi', ['Hello']));
    }

    public function test_without_a_key_it_falls_back_to_the_env_mailer(): void
    {
        Settings::set(['mail_mailer' => 'resend', 'resend_api_key' => null]);
        MailSettings::apply();
        $this->assertNotSame('resend_api', config('mail.default'));
    }
}
