<?php

namespace App\Filament\Support;

use App\Support\ConnectionProblem;
use App\Support\Translator;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Throwable;

/** "Translate" button for an Arabic field; fills it from its English twin. */
class ArabicField
{
    public static function translate(string $englishField, string $arabicField): Action
    {
        return Action::make('translate_'.str_replace('.', '_', $arabicField))
            ->label('Translate')
            ->tooltip('Translate the English text into Arabic')
            ->icon('heroicon-o-language')
            ->color('primary')
            ->action(function (Get $get, Set $set, Component $component, $livewire) use ($englishField, $arabicField) {
                try {
                    $set($arabicField, Translator::toArabic($get($englishField)));
                    Notification::make()->success()->title('Translated — please check the Arabic before saving')->send();
                } catch (ConnectionProblem $e) {
                    if (Translator::provider() !== 'mymemory') {
                        Notification::make()->warning()->title($e->getMessage())->persistent()->send();

                        return;
                    }
                    // The server cannot reach the service (often missing SSL certificates on Windows PHP):
                    // translate in the admin's own browser instead, which trusts the same sites Chrome does.
                    $livewire->js(self::browserScript((string) $get($englishField), $component->getStatePath()));
                } catch (Throwable $e) {
                    Notification::make()->warning()->title($e->getMessage())->send();
                }
            });
    }

    public static function browserScript(string $text, string $statePath): string
    {
        return sprintf(<<<'JS'
(async () => {
    const text = %s, path = %s;
    try {
        const out = [];
        for (const line of text.split(/\r?\n/)) {
            if (!line.trim()) { out.push(line); continue; }
            const r = await fetch('https://api.mymemory.translated.net/get?langpair=en%%7Car&q=' + encodeURIComponent(line.slice(0, 480)));
            const j = await r.json();
            if (j.responseStatus != 200) throw new Error(j.responseDetails || 'limit reached');
            const t = document.createElement('textarea'); t.innerHTML = j.responseData.translatedText; out.push(t.value);
        }
        $wire.set(path, out.join('\n'));
        new FilamentNotification().title('Translated (via your browser) — please check the Arabic before saving').success().send();
    } catch (e) {
        new FilamentNotification().title('Translation failed: ' + e.message).warning().send();
    }
})();
JS, json_encode($text, JSON_UNESCAPED_UNICODE), json_encode($statePath));
    }
}
