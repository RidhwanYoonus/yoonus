<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Models\User;
use App\Support\Settings;
use App\Support\Translator;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ArabicTranslateTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_translator_is_used_without_keys_and_keeps_lines(): void
    {
        Http::fake(['api.mymemory.translated.net/*' => fn (Request $r) => Http::response([
            'responseStatus' => 200, 'responseData' => ['translatedText' => ['Gold ring' => 'خاتم ذهب', 'Hallmarked.' => 'مدموغ.'][$r['q']] ?? '؟'],
        ])]);
        $this->assertSame('mymemory', Translator::provider());
        $this->assertSame("خاتم ذهب\nمدموغ.", Translator::toArabic("Gold ring\nHallmarked."));
        Http::assertSent(fn (Request $r) => $r['langpair'] === 'en|ar');
    }

    public function test_google_and_deepl_are_used_when_keys_are_set(): void
    {
        Http::fake([
            'translation.googleapis.com/*' => Http::response(['data' => ['translations' => [['translatedText' => 'قلادة &quot;ذهب&quot;']]]]),
            'api-free.deepl.com/*' => Http::response(['translations' => [['text' => 'أقراط']]]),
        ]);
        Settings::set(['google_translate_key' => 'g-key']);
        $this->assertSame('قلادة "ذهب"', Translator::toArabic('Necklace "gold"'));

        Settings::set(['translation_provider' => 'deepl', 'deepl_key' => 'abc:fx']);
        $this->assertSame('أقراط', Translator::toArabic('Earrings'));
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'DeepL-Auth-Key abc:fx') && $r['target_lang'] === 'AR');
    }

    public function test_translate_button_fills_arabic_name_on_product_form(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        Http::fake(['api.mymemory.translated.net/*' => Http::response(['responseStatus' => 200, 'responseData' => ['translatedText' => 'خاتم كلاسيكي']])]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Classic Ring'])
            ->callFormComponentAction('name_ar', 'translate_name_ar')
            ->assertFormSet(['name_ar' => 'خاتم كلاسيكي']);
    }
}
