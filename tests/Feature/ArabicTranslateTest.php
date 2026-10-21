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
            'responseStatus' => 200, 'responseData' => ['translatedText' => ['Gold ring' => 'خاتم ذهب', 'Polished finish.' => 'لمسة لامعة.'][$r['q']] ?? '؟'],
        ])]);
        $this->assertSame('mymemory', Translator::provider());
        $this->assertSame("خاتم ذهب\nلمسة لامعة.", Translator::toArabic("Gold ring\nPolished finish."));
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
        $this->assertSame('أقراط', Translator::toArabic('Earrings with blue stones'));
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'DeepL-Auth-Key abc:fx') && $r['target_lang'] === 'AR');
    }

    public function test_translate_button_fills_arabic_name_on_product_form(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        Settings::set(['translation_provider' => 'google', 'google_translate_key' => 'k']);
        Http::fake(['translation.googleapis.com/*' => Http::response(['data' => ['translations' => [['translatedText' => 'خاتم كلاسيكي']]]])]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Classic Ring'])
            ->callFormComponentAction('name_ar', 'translate_name_ar')
            ->assertFormSet(['name_ar' => 'خاتم كلاسيكي']);

        // Dictionary words are filled instantly, without any service.
        Livewire::test(CreateProduct::class)->fillForm(['name' => 'Necklaces'])
            ->callFormComponentAction('name_ar', 'translate_name_ar')->assertFormSet(['name_ar' => 'قلائد']);
    }

    public function test_free_translate_button_runs_in_the_browser(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        Http::fake();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $page = Livewire::test(CreateProduct::class)->fillForm(['name' => 'Classic Ring'])->callFormComponentAction('name_ar', 'translate_name_ar');
        Http::assertNothingSent();
        $this->assertStringContainsString('Classic Ring', json_encode($page->effects['xjs'] ?? [], JSON_UNESCAPED_UNICODE));
    }

    public function test_when_server_cannot_connect_the_browser_translates_instead(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 60: SSL certificate problem: unable to get local issuer certificate'));
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $page = Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Classic Ring'])
            ->callFormComponentAction('name_ar', 'translate_name_ar');
        $js = json_encode($page->effects['xjs'] ?? []);
        $this->assertStringContainsString('api.mymemory.translated.net', $js);
        $this->assertStringContainsString('data.name_ar', $js);
    }

    public function test_bulk_button_fills_only_blank_arabic_fields(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $karat = \App\Models\GoldCategory::where('name', '22K')->value('id');
        $blank = \App\Models\Product::create(['name' => 'Gold Ring', 'description' => 'Polished finish', 'sku' => 'T-1', 'slug' => 't-1', 'gold_category_id' => $karat, 'weight_grams' => 2, 'stock_qty' => 1]);
        $done = \App\Models\Product::create(['name' => 'Bangle', 'name_ar' => 'سوار يدوي', 'sku' => 'T-2', 'slug' => 't-2', 'gold_category_id' => $karat, 'weight_grams' => 2, 'stock_qty' => 1]);
        Settings::set(['translation_provider' => 'google', 'google_translate_key' => 'k']);
        Http::fake(['translation.googleapis.com/*' => fn (Request $r) => Http::response(['data' => ['translations' => [['translatedText' => 'AR:'.$r['q']]]]])]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Livewire::test(\App\Filament\Resources\ProductResource\Pages\ListProducts::class)->callAction('translateMissingArabic');

        $this->assertSame('AR:Gold Ring', $blank->fresh()->name_ar);
        $this->assertSame('AR:Polished finish', $blank->fresh()->description_ar);
        $this->assertSame('سوار يدوي', $done->fresh()->name_ar);   // untouched
    }

    public function test_free_bulk_translation_runs_in_the_browser_without_server_waits(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $karat = \App\Models\GoldCategory::where('name', '22K')->value('id');
        $p = \App\Models\Product::create(['name' => 'Twisted Pair', 'sku' => 'T-3', 'slug' => 't-3', 'gold_category_id' => $karat, 'weight_grams' => 2, 'stock_qty' => 1]);
        Http::fake();
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $page = Livewire::test(\App\Filament\Resources\ProductResource\Pages\ListProducts::class)->callAction('translateMissingArabic');
        Http::assertNothingSent();
        $this->assertStringContainsString('Twisted Pair', json_encode($page->effects['xjs'] ?? [], JSON_UNESCAPED_UNICODE));
    }

    public function test_bulk_button_hands_over_to_browser_when_server_cannot_connect(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $karat = \App\Models\GoldCategory::where('name', '22K')->value('id');
        $p = \App\Models\Product::create(['name' => 'Gold Ring', 'sku' => 'T-1', 'slug' => 't-1', 'gold_category_id' => $karat, 'weight_grams' => 2, 'stock_qty' => 1]);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 60: SSL certificate problem'));
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $page = Livewire::test(\App\Filament\Resources\ProductResource\Pages\ListProducts::class)->callAction('translateMissingArabic');
        $this->assertStringContainsString('saveMissingArabic', json_encode($page->effects['xjs'] ?? []));

        // What the browser then calls back with:
        $page->call('saveMissingArabic', $p->id, 'name_ar', 'خاتم ذهب');
        $this->assertSame('خاتم ذهب', $p->fresh()->name_ar);
        $page->call('saveMissingArabic', $p->id, 'name_ar', 'something else');   // already filled: kept
        $page->call('saveMissingArabic', $p->id, 'sku', 'HACK');                   // not an Arabic field
        $this->assertSame('خاتم ذهب', $p->fresh()->name_ar);
        $this->assertSame('T-1', $p->fresh()->sku);
    }

    public function test_jewellery_words_use_the_dictionary_and_owner_words_win(): void
    {
        Http::fake();
        $this->assertSame('خواتم', Translator::toArabic('Rings'));          // not "phone calls"
        $this->assertSame('أقراط', Translator::toArabic('  earrings. '));
        Settings::set(['translation_glossary' => "Kada = كادا\nRings = خواتم ذهب"]);
        $this->assertSame('كادا', Translator::toArabic('kada'));
        $this->assertSame('خواتم ذهب', Translator::toArabic('Rings'));
        Http::assertNothingSent();
    }

    public function test_free_translator_prefers_machine_translation_over_crowd_entries(): void
    {
        Http::fake(['api.mymemory.translated.net/*' => Http::response(['responseStatus' => 200,
            'responseData' => ['translatedText' => 'اتصال'],
            'matches' => [['created-by' => 'someone', 'translation' => 'اتصال'], ['created-by' => 'MT!', 'translation' => 'خاتم مصنوع يدويا']]])]);
        $this->assertSame('خاتم مصنوع يدويا', Translator::toArabic('Handmade ring'));
    }

    public function test_bulk_redo_replaces_earlier_wrong_arabic(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $cat = \App\Models\Category::create(['name' => 'Rings', 'name_ar' => 'اتصال', 'slug' => 'rings-x']);
        Http::fake();
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Livewire::test(\App\Filament\Resources\CategoryResource\Pages\ManageCategories::class)->callAction('translateMissingArabic', ['overwrite' => false]);
        $this->assertSame('اتصال', $cat->fresh()->name_ar);
        Livewire::test(\App\Filament\Resources\CategoryResource\Pages\ManageCategories::class)->callAction('translateMissingArabic', ['overwrite' => true]);
        $this->assertSame('خواتم', $cat->fresh()->name_ar);
    }

    public function test_shop_wording_has_fixed_arabic(): void
    {
        foreach (['Exclusive Product' => 'منتج حصري', 'Exclusive Item' => 'قطعة حصرية', 'Special Product' => 'منتج مميز', 'Item' => 'قطعة',
            'Royal Designs' => 'تصاميم ملكية', ' pieces ' => 'قطع', 'Kada' => 'كادا', 'Lab-grown diamond' => 'ألماس مصنّع في المختبر'] as $en => $ar) {
            $this->assertSame($ar, \App\Support\Translator::toArabic($en), $en);
        }
    }
}
