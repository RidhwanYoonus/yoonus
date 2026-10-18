<?php

namespace App\Filament\Support;

use App\Support\Translator;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Throwable;

/** "Translate from English" button for an Arabic field; fills it from its English twin. */
class ArabicField
{
    public static function translate(string $englishField, string $arabicField): Action
    {
        return Action::make('translate_'.str_replace('.', '_', $arabicField))
            ->label('Translate')->tooltip('Translate the English text into Arabic')
            ->icon('heroicon-o-language')
            ->color('primary')
            ->action(function (Get $get, Set $set) use ($englishField, $arabicField) {
                try {
                    $set($arabicField, Translator::toArabic($get($englishField)));
                    Notification::make()->success()->title('Translated — please check the Arabic before saving')->send();
                } catch (Throwable $e) {
                    Notification::make()->warning()->title($e->getMessage())->send();
                }
            });
    }
}
