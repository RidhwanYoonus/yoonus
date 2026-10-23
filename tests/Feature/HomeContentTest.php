<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HomeContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
    }

    public function test_default_boxes_no_longer_promise_pay_later_or_delivery(): void
    {
        Settings::set(['tabby_enabled' => false, 'tamara_enabled' => false]);
        $this->get('/')->assertOk()->assertSee('Collect in store')->assertSee('Secure payment')
            ->assertDontSee('Pay in 4')->assertDontSee('Insured delivery')->assertDontSee('Tabby');
    }

    public function test_admin_changes_banner_text_and_boxes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('Home page');
        Livewire::test(\App\Filament\Pages\Settings::class)
            ->set('data.home_headline', 'Direct from our factory')
            ->set('data.home_headline_ar', 'مباشرة من مصنعنا')
            ->set('data.home_box3_title', 'Free gift wrap')->set('data.home_box3_text', 'On every order')->set('data.home_box3_icon', 'gift')
            ->call('save')->assertHasNoErrors();
        auth()->logout();

        $this->get('/')->assertSee('Direct from our factory')->assertSee('Free gift wrap')->assertSee('On every order')
            ->assertDontSee('Gold, priced honestly');
        $this->get('/?lang=ar')->assertSee('مباشرة من مصنعنا');
    }

    public function test_footer_lists_only_payment_methods_that_are_on(): void
    {
        Settings::set(['tabby_enabled' => true, 'tamara_enabled' => false, 'cod_enabled' => false, 'applepay_enabled' => true]);
        $this->get('/')->assertSee('Card · Apple Pay', false)->assertSee('Tabby')->assertDontSee('Tamara')->assertDontSee('Cash on delivery');
    }
}
