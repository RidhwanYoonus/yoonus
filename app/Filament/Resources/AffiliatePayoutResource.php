<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasStaffArea;
use App\Filament\Resources\AffiliatePayoutResource\Pages;
use App\Models\Affiliate;
use App\Models\AffiliatePayout;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Every cash collection by an affiliate at the store. */
class AffiliatePayoutResource extends Resource
{
    use HasStaffArea;

    protected static string $staffArea = 'affiliate_payouts';

    protected static ?string $model = AffiliatePayout::class;

    protected static ?string $slug = 'affiliate-collections';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Affiliates';

    protected static ?string $navigationLabel = 'Collections';

    protected static ?string $pluralModelLabel = 'Collections at store';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table, bool $withAffiliate = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['affiliate.user', 'paidBy', 'branch']))
            ->defaultSort('paid_at', 'desc')
            ->columns(array_values(array_filter([
                Tables\Columns\TextColumn::make('paid_at')->label('Date')->dateTime('d M Y, H:i')->sortable(),
                $withAffiliate ? Tables\Columns\TextColumn::make('affiliate.code')->label('Affiliate')->badge()
                    ->description(fn (AffiliatePayout $r) => $r->affiliate?->user?->name)->searchable() : null,
                Tables\Columns\TextColumn::make('amount')->money('AED')->weight('bold')->summarize(Sum::make()->money('AED')->label('')),
                Tables\Columns\TextColumn::make('branch.name')->label('Store')->placeholder('—'),
                Tables\Columns\TextColumn::make('paidBy.name')->label('Handed over by')->placeholder('—'),
                Tables\Columns\TextColumn::make('reference')->label('Receipt no.')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('note')->limit(40)->toggleable(),
            ])))
            ->filters(array_values(array_filter([
                $withAffiliate ? Tables\Filters\SelectFilter::make('affiliate_id')->label('Affiliate')->multiple()
                    ->options(fn () => Affiliate::with('user')->get()->mapWithKeys(fn ($a) => [$a->id => $a->code.' — '.$a->user?->name])) : null,
                Tables\Filters\Filter::make('paid_at')->form([Forms\Components\DatePicker::make('from'), Forms\Components\DatePicker::make('until')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('paid_at', '>=', $v))
                        ->when($data['until'] ?? null, fn ($q, $v) => $q->whereDate('paid_at', '<=', $v))),
            ])))
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAffiliatePayouts::route('/')];
    }
}
