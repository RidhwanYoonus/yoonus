<?php

namespace App\Filament\Concerns;

use App\Support\ConnectionProblem;
use App\Support\StaffAccess;
use App\Support\Translator;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * List-page button "Translate missing Arabic": fills every blank Arabic field from its English
 * twin. Uses the server when it can connect; otherwise the admin's browser does the translating
 * and sends each result back through saveMissingArabic().
 */
trait TranslatesMissingArabic
{
    /** English column => Arabic column, e.g. ['name' => 'name_ar']. */
    abstract protected function arabicPairs(): array;

    protected function translateMissingArabicAction(): Action
    {
        return Action::make('translateMissingArabic')
            ->label('Translate missing Arabic')
            ->icon('heroicon-o-language')
            ->color('gray')
            ->visible(fn () => StaffAccess::current(static::getResource()::staffArea(), 'edit'))
            ->modalHeading('Translate missing Arabic')
            ->modalDescription(fn () => (($n = count($this->missingArabic())) === 0 ? 'Every Arabic field is already filled in.' : "{$n} blank Arabic field(s) will be filled from the English text.")
                .' Please check the results afterwards.')
            ->form([
                \Filament\Forms\Components\Toggle::make('overwrite')->label('Redo all Arabic (also replace Arabic that is already filled in)')
                    ->helperText('Use this to fix earlier machine translations. Arabic you typed yourself will be replaced too.'),
            ])
            ->modalSubmitActionLabel('Translate')
            ->action(function (array $data) {
                $overwrite = (bool) ($data['overwrite'] ?? false);
                $jobs = $this->missingArabic(overwrite: $overwrite);
                if ($jobs === []) {
                    Notification::make()->success()->title('Nothing to translate — all Arabic fields are filled in')->send();

                    return;
                }
                // The free service works from the browser even when this PC's PHP cannot make secure
                // connections, and the browser has no 30-second limit — so bulk jobs go there directly.
                // Dictionary words (Rings, Necklaces, your own list…) are filled straight away.
                $known = 0;
                foreach ($jobs as $i => $job) {
                    if (($ar = \App\Support\JewelleryGlossary::lookup($job['text'])) !== null) {
                        $known += (int) $this->saveMissingArabic($job['id'], $job['field'], $ar, $overwrite);
                        unset($jobs[$i]);
                    }
                }
                if ($jobs === []) {
                    Notification::make()->success()->title("Translated {$known} field(s) — please check the Arabic")->send();

                    return;
                }
                if (Translator::provider() === 'mymemory' || \App\Support\SecureHttp::isDown()) {
                    $this->js($this->browserTranslateScript(array_values($jobs), $known, $overwrite));

                    return;
                }
                @set_time_limit(180);
                $started = microtime(true);
                $done = 0;
                try {
                    foreach ($jobs as $i => $job) {
                        if (microtime(true) - $started > 20) {
                            Notification::make()->success()->title("Translated {$done} field(s) so far — click the button again to continue")->persistent()->send();

                            return;
                        }
                        $this->saveMissingArabic($job['id'], $job['field'], Translator::toArabic($job['text']), $overwrite);
                        $done++;
                        unset($jobs[$i]);
                    }
                    Notification::make()->success()->title("Translated {$done} field(s) — please check the Arabic")->send();
                } catch (ConnectionProblem $e) {
                    if (Translator::provider() !== 'mymemory') {
                        Notification::make()->warning()->title($e->getMessage())->persistent()->send();

                        return;
                    }
                    $this->js($this->browserTranslateScript(array_values($jobs), $done, $overwrite));
                } catch (Throwable $e) {
                    Notification::make()->warning()->title(($done ? "Translated {$done}, then stopped: " : '').$e->getMessage())->persistent()->send();
                }
            });
    }

    /** @return array<int,array{id:int,field:string,text:string}> */
    protected function missingArabic(int $limit = 300, bool $overwrite = false): array
    {
        $model = static::getResource()::getModel();
        $jobs = [];
        foreach ($this->arabicPairs() as $en => $ar) {
            $model::query()
                ->when(! $overwrite, fn (Builder $q) => $q->where(fn (Builder $q) => $q->whereNull($ar)->orWhere($ar, '')))
                ->where(fn (Builder $q) => $q->whereNotNull($en)->where($en, '!=', ''))
                ->orderBy('id')->limit($limit)->get(['id', $en])
                ->each(function ($r) use (&$jobs, $en, $ar) {
                    $jobs[] = ['id' => $r->id, 'field' => $ar, 'text' => (string) $r->{$en}];
                });
        }

        return array_slice($jobs, 0, $limit);
    }

    /** Called by the browser fallback for each translated field; only fills fields that are still blank. */
    public function saveMissingArabic(int $id, string $field, string $value, bool $overwrite = false): bool
    {
        if (! in_array($field, $this->arabicPairs(), true) || ! StaffAccess::current(static::getResource()::staffArea(), 'edit')) {
            return false;
        }
        $record = static::getResource()::getModel()::find($id);
        $value = trim(strip_tags($value));
        if (! $record || (! $overwrite && filled($record->{$field})) || $value === '') {
            return false;
        }
        $record->forceFill([$field => mb_substr($value, 0, $field === 'description_ar' ? 5000 : 255)])->save();

        return true;
    }

    protected function browserTranslateScript(array $jobs, int $alreadyDone, bool $overwrite = false): string
    {
        return \App\Filament\Support\ArabicField::jsHelpers().sprintf(<<<'JS'
(async () => {
    const jobs = %s, overwrite = %s;
    let done = %d, failed = '';
    const note = (t, kind) => { const n = new FilamentNotification().title(t); (kind === 'ok' ? n.success() : n.warning()).send(); };
    note('Translating ' + jobs.length + ' field(s) in your browser… keep this page open', 'ok');
    for (const job of jobs) {
        try {
            const out = [];
            for (const line of job.text.split(/\r?\n/)) out.push(line.trim() ? await window.jsTrAr(line) : line);
            if (await $wire.saveMissingArabic(job.id, job.field, out.join('\n'), overwrite)) done++;
        } catch (e) { failed = e.message; break; }
    }
    note(failed ? ('Translated ' + done + ' field(s), then stopped: ' + failed) : ('Translated ' + done + ' field(s) — please check the Arabic'), failed ? 'warn' : 'ok');
    $wire.$refresh();
})();
JS, json_encode($jobs, JSON_UNESCAPED_UNICODE), json_encode($overwrite), $alreadyDone);
    }
}
