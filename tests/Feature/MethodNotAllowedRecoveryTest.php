<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MethodNotAllowedRecoveryTest extends TestCase
{
    public function test_form_sent_to_a_page_address_reopens_the_page_and_is_logged(): void
    {
        Log::spy();
        $this->post('/shop')->assertStatus(303)->assertRedirect(url('/shop'));
        Log::shouldHaveReceived('warning')->withArgs(fn ($msg, $ctx) => $msg === '405 method not allowed' && $ctx['method'] === 'POST');
    }

    public function test_live_admin_request_gets_the_refresh_prompt_instead_of_the_error_page(): void
    {
        $this->post('/shop', [], ['X-Livewire' => 'true'])->assertStatus(419);
    }
}
