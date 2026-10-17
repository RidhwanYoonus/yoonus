<?php

namespace App\Filament\Resources\GoldCategoryResource\Pages;

use App\Filament\Resources\GoldCategoryResource;
use App\Filament\Widgets\RecalcProgress;
use App\Models\GoldCategory;
use App\Pricing\GoldRateService;
use App\Pricing\WebsiteGoldRates;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ListGoldCategories extends ListRecords
{
    protected static string $resource = GoldCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->fetchFromWebsiteAction(),
            Actions\CreateAction::make()->label('Add karat')->color('gray'),
        ];
    }

    /** Pull today's rates from the configured website, let the admin check them, then apply. */
    private function fetchFromWebsiteAction(): Actions\Action
    {
        $karats = GoldCategory::where('is_active', true)->orderByDesc('karat')->get()
            ->filter(fn (GoldCategory $c) => in_array((int) $c->karat, WebsiteGoldRates::KARATS, true));

        return Actions\Action::make('fetchWebsiteRates')
            ->label('Fetch rate from Dubai City of Gold')
            ->icon('heroicon-o-cloud-arrow-down')
            ->modalHeading('Today\'s gold rate from the website')
            ->modalDescription(fn () => 'Source: '.WebsiteGoldRates::url().' — check the numbers before applying.')
            ->modalSubmitActionLabel('Apply rates')
            ->fillForm(function () {
                try {
                    $rates = app(WebsiteGoldRates::class)->fetch();

                    return ['error' => null, 'mode' => 'base', 'fetched_at' => now()->format('d M Y, H:i')] + collect($rates)->mapWithKeys(fn ($v, $k) => ["rate_{$k}" => $v])->all();
                } catch (Throwable $e) {
                    return ['error' => $e->getMessage(), 'mode' => 'base'];
                }
            })
            ->form([
                Forms\Components\Placeholder::make('status')->hiddenLabel()
                    ->content(fn (Forms\Get $get) => $get('error')
                        ? '⚠ '.$get('error').' Use "Paste from website" below instead, or type the rates by hand.'
                        : 'Rates read at '.$get('fetched_at').'. Empty boxes were not found on the page.'),
                Forms\Components\Textarea::make('pasted')->label('Paste from website')->rows(3)
                    ->placeholder('On dubaicityofgold.com, select the rate card (24K GOLD … 18K GOLD), press Ctrl+C, then click here and press Ctrl+V.')
                    ->live(debounce: 300)
                    ->afterStateUpdated(function (?string $state, Forms\Set $set) {
                        foreach (app(WebsiteGoldRates::class)->parse(nl2br(e((string) $state))) as $karat => $value) {
                            $set("rate_{$karat}", $value);
                        }
                    })
                    ->dehydrated(false),
                Forms\Components\Hidden::make('error'),
                Forms\Components\Hidden::make('fetched_at'),
                Forms\Components\Grid::make(4)->schema(
                    collect(WebsiteGoldRates::KARATS)->map(fn ($k) => Forms\Components\TextInput::make("rate_{$k}K")
                        ->label("{$k}K (AED/g)")->numeric()->minValue(1)->prefix('AED'))->all()
                ),
                Forms\Components\Radio::make('mode')->label('How to apply')->required()->options([
                    'base' => 'Use the 24K rate and derive 22K/21K/18K by purity (recommended)',
                    'each' => 'Use the website\'s rate for every karat',
                ]),
            ])
            ->action(function (array $data) use ($karats) {
                $service = app(GoldRateService::class);
                $base = GoldCategory::where('auto_derive', false)->orderByDesc('karat')->first();
                $value24 = $data['rate_24K'] ?? null;
                if (! $value24 && ! empty($data['rate_22K'])) {
                    $value24 = round((float) $data['rate_22K'] * 24 / 22, 2);   // derive 24K from 22K if only 22K was found
                }
                if (! $base || ! $value24) {
                    Notification::make()->danger()->title('A 24K (or 22K) rate is needed.')->send();

                    return;
                }

                try {
                    $service->updateRate($base, $value24, 'gram', Auth::id(), 'website');
                    $applied = ["{$base->name} AED {$value24}"];

                    if ($data['mode'] === 'each') {
                        foreach ($karats as $cat) {
                            $v = $data['rate_'.(int) $cat->karat.'K'] ?? null;
                            if ($v && $cat->id !== $base->id) {
                                $service->updateRate($cat->fresh(), $v, 'gram', Auth::id(), 'website');
                                $applied[] = "{$cat->name} AED {$v}";
                            }
                        }
                    }

                    Notification::make()->success()->title('Gold rates updated from website')
                        ->body(implode(' · ', $applied).'. Product prices are being recalculated.')->send();
                } catch (Throwable $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                }
            });
    }

    protected function getHeaderWidgets(): array
    {
        return [RecalcProgress::class];
    }
}
