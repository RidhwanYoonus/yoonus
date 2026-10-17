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

    /**
     * Pull today's rates from the configured website for every active karat, let the admin
     * check them, then set each karat to the website's rate.
     */
    private function fetchFromWebsiteAction(): Actions\Action
    {
        return Actions\Action::make('fetchWebsiteRates')
            ->label('Fetch rate from Dubai City of Gold')
            ->icon('heroicon-o-cloud-arrow-down')
            ->modalHeading('Today\'s gold rate from the website')
            ->modalDescription(fn () => 'Source: '.WebsiteGoldRates::url().' — check the numbers, then apply. Each karat is set to the website\'s rate.')
            ->modalSubmitActionLabel('Apply rates')
            ->fillForm(function () {
                try {
                    $rates = app(WebsiteGoldRates::class)->fetch();

                    return ['error' => null, 'fetched_at' => now()->format('d M Y, H:i')] + collect($rates)->mapWithKeys(fn ($v, $k) => ["rate_{$k}" => $v])->all();
                } catch (Throwable $e) {
                    return ['error' => $e->getMessage()];
                }
            })
            ->form(fn () => [
                Forms\Components\Placeholder::make('status')->hiddenLabel()
                    ->content(fn (Forms\Get $get) => $get('error')
                        ? '⚠ '.$get('error').' Use "Paste from website" below instead, or type the rates by hand.'
                        : 'Rates read at '.$get('fetched_at').'. Empty boxes were not found on the page — leave them empty to keep the current rate.'),
                Forms\Components\Textarea::make('pasted')->label('Paste from website')->rows(3)
                    ->placeholder('On dubaicityofgold.com, select the rate card (24K GOLD … 14K GOLD), press Ctrl+C, then click here and press Ctrl+V.')
                    ->live(debounce: 300)
                    ->afterStateUpdated(function (?string $state, Forms\Set $set) {
                        foreach (app(WebsiteGoldRates::class)->parse(nl2br(e((string) $state))) as $karat => $value) {
                            $set("rate_{$karat}", $value);
                        }
                    })
                    ->dehydrated(false),
                Forms\Components\Hidden::make('error'),
                Forms\Components\Hidden::make('fetched_at'),
                Forms\Components\Grid::make(['default' => 2, 'md' => 5])->schema(
                    collect(WebsiteGoldRates::karats())->map(fn ($k) => Forms\Components\TextInput::make("rate_{$k}K")
                        ->label("{$k}K (AED/g)")->numeric()->minValue(1)->prefix('AED')
                        ->placeholder(fn () => GoldCategory::where('karat', $k)->value('rate_per_gram')))->all()
                ),
            ])
            ->action(function (array $data) {
                $service = app(GoldRateService::class);
                // Every karat gets the website's own rate, independently.
                $categories = GoldCategory::where('is_active', true)->orderByDesc('karat')->get();
                $applied = [];

                try {
                    foreach ($categories as $cat) {
                        $value = $data['rate_'.(int) $cat->karat.'K'] ?? null;
                        if (is_numeric($value) && (float) $value > 0) {
                            $service->updateRate($cat->fresh(), $value, 'gram', Auth::id(), 'website');
                            $applied[] = "{$cat->name} AED ".number_format((float) $value, 2);
                        }
                    }
                } catch (Throwable $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }

                if (! $applied) {
                    Notification::make()->warning()->title('No rates to apply — fill in at least one karat.')->send();

                    return;
                }

                Notification::make()->success()->title('Gold rates updated from website')
                    ->body(implode(' · ', $applied).'. Product prices are being recalculated.')->send();
            });
    }

    protected function getHeaderWidgets(): array
    {
        return [RecalcProgress::class];
    }
}
