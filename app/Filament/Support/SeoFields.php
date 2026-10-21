<?php

namespace App\Filament\Support;

use App\Models\Category;
use App\Models\Product;
use App\Support\SeoText;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;

/** SEO boxes (English + Arabic) with "Fill empty SEO" and a "Write" button per box, for products and categories. */
class SeoFields
{
    private const SOURCE = [
        Product::class => ['name', 'name_ar', 'description', 'description_ar', 'gold_category_id', 'category_id', 'weight_grams', 'stone_type',
            'diamond_origin', 'diamond_carat', 'diamond_color', 'diamond_clarity', 'diamond_cut', 'certificate_lab_id'],
        Category::class => ['name', 'name_ar'],
    ];

    public static function section(string $modelClass): Forms\Components\Section
    {
        return Forms\Components\Section::make('SEO')->collapsible()->columns(2)
            ->description('Shown in Google and when the link is shared. Empty boxes are written automatically from the details above when you save; you can also fill them now.')
            ->headerActions([
                Forms\Components\Actions\Action::make('fillSeo')->label('Fill empty SEO')->icon('heroicon-o-sparkles')->color('warning')
                    ->action(function (Get $get, Set $set, ?\Illuminate\Database\Eloquent\Model $record) use ($modelClass) {
                        $texts = self::texts($modelClass, $get, $record);
                        $filled = 0;
                        foreach (SeoText::FIELDS as $field) {
                            if (blank($get($field)) && filled($texts[$field] ?? null)) {
                                $set($field, $texts[$field]);
                                $filled++;
                            }
                        }
                        Notification::make()->title($filled ? "Filled {$filled} empty SEO ".str('box')->plural($filled) : 'All SEO boxes already have text')
                            ->body($filled ? 'Check the text, then save.' : 'Use "Write" on a box to replace its text.')->success()->send();
                    }),
            ])
            ->schema([
                self::box($modelClass, 'meta_title', 'Page title (English)', 60),
                self::box($modelClass, 'meta_title_ar', 'Page title (Arabic)', 60, rtl: true),
                self::box($modelClass, 'meta_description', 'Description (English)', 158, textarea: true),
                self::box($modelClass, 'meta_description_ar', 'Description (Arabic)', 158, textarea: true, rtl: true),
            ]);
    }

    private static function box(string $modelClass, string $field, string $label, int $ideal, bool $textarea = false, bool $rtl = false)
    {
        $c = $textarea ? Forms\Components\Textarea::make($field)->rows(3)->maxLength(320) : Forms\Components\TextInput::make($field)->maxLength(255);

        return $c->label($label)->live(onBlur: true)
            ->extraInputAttributes($rtl ? ['dir' => 'rtl'] : [])
            ->helperText(fn (?string $state) => mb_strlen((string) $state).' / '.$ideal.' characters'.(mb_strlen((string) $state) > $ideal ? ' — Google may cut the end' : ''))
            ->hintAction(Forms\Components\Actions\Action::make('write_'.$field)->label('Write')->icon('heroicon-o-sparkles')
                ->action(fn (Get $get, Set $set, ?\Illuminate\Database\Eloquent\Model $record) => $set($field, self::texts($modelClass, $get, $record)[$field] ?? null)));
    }

    /** SEO text from what is in the form right now (saved or not). */
    private static function texts(string $modelClass, Get $get, ?\Illuminate\Database\Eloquent\Model $record = null): array
    {
        $model = new $modelClass;
        if ($record) {
            $model->id = $record->getKey();   // a category's karats / diamonds come from its products
        }
        foreach (self::SOURCE[$modelClass] as $field) {
            $model->setAttribute($field, $get($field));
        }

        return $model instanceof Product ? SeoText::forProduct($model) : SeoText::forCategory($model);
    }
}
