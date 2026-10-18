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
                $english = trim((string) $get($englishField));
                // Free service: let the browser do it (works even when this PC's PHP cannot make
                // secure connections, and never hits PHP's time limit). Dictionary words stay instant.
                if ($english !== '' && \App\Support\JewelleryGlossary::lookup($english) === null
                    && (Translator::provider() === 'mymemory' || \App\Support\SecureHttp::isDown())) {
                    $livewire->js(self::browserScript($english, $component->getStatePath()));

                    return;
                }
                @set_time_limit(120);
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

    /**
     * Browser-side translator used when the server cannot connect: jewellery dictionary first,
     * then MyMemory, preferring its machine translation over crowd entries. Defines window.jsTrAr(line).
     */
    public static function jsHelpers(): string
    {
        $glossary = json_encode(\App\Support\JewelleryGlossary::all(), JSON_UNESCAPED_UNICODE);
        $email = json_encode((string) (\App\Support\Settings::get('mymemory_email') ?: \App\Support\Settings::get('store_email')));

        return <<<JS
window.jsTrAr = window.jsTrAr || (() => {
    const glossary = {$glossary}, email = {$email};
    const key = (s) => s.trim().replace(/[.!:;,]+$/, '').replace(/\\s+/g, ' ').toLowerCase();
    const decode = (s) => { const t = document.createElement('textarea'); t.innerHTML = s; return t.value; };
    return async (line) => {
        if (glossary[key(line)]) return glossary[key(line)];
        const r = await fetch('https://api.mymemory.translated.net/get?langpair=en%7Car&mt=1&q=' + encodeURIComponent(line.slice(0, 480)) + (email ? '&de=' + encodeURIComponent(email) : ''));
        const j = await r.json();
        if (j.responseStatus != 200) throw new Error(j.responseDetails || 'daily limit reached');
        const mt = (j.matches || []).find((m) => m['created-by'] === 'MT!' && m.translation);
        return decode(mt ? mt.translation : j.responseData.translatedText);
    };
})();
JS;
    }

    public static function browserScript(string $text, string $statePath): string
    {
        return self::jsHelpers().sprintf(<<<'JS'
(async () => {
    const text = %s, path = %s;
    try {
        const out = [];
        for (const line of text.split(/\r?\n/)) out.push(line.trim() ? await window.jsTrAr(line) : line);
        $wire.set(path, out.join('\n'));
        new FilamentNotification().title('Translated (via your browser) — please check the Arabic before saving').success().send();
    } catch (e) {
        new FilamentNotification().title('Translation failed: ' + e.message).warning().send();
    }
})();
JS, json_encode($text, JSON_UNESCAPED_UNICODE), json_encode($statePath));
    }
}
