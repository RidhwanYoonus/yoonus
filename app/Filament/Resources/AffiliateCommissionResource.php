<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasStaffArea;
use App\Filament\Resources\AffiliateCommissionResource\Pages;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Detailed report: every commission line, per order item. */
class AffiliateCommissionResource extends Resource
{
    use HasStaffArea;

    protected static string $staffArea = 'affiliates';

    protected static ?string $model = AffiliateCommission::class;

    protected static ?string $slug = 'affiliate-commissions';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Affiliates';

    protected static ?string $navigationLabel = 'Commission report';

    protected static ?string $pluralModelLabel = 'Commission report';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table, bool $withAffiliate = true): Table
    {
        $sum = fn () => Sum::make()->money('AED')->label('');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['affiliate.user', 'order']))
            ->defaultSort('created_at', 'desc')
            ->columns(array_values(array_filter([
                Tables\Columns\TextColumn::make('created_at')->label('Date')->dateTime('d M Y')->sortable(),
                $withAffiliate ? Tables\Columns\TextColumn::make('affiliate.code')->label('Affiliate')->badge()
                    ->description(fn (AffiliateCommission $r) => $r->affiliate?->user?->name)->searchable() : null,
                Tables\Columns\TextColumn::make('order.number')->label('Order')->searchable()
                    ->url(fn (AffiliateCommission $r) => $r->order ? OrderResource::getUrl('view', ['record' => $r->order]) : null)
                    ->description(fn (AffiliateCommission $r) => $r->order?->statusLabel()),
                Tables\Columns\TextColumn::make('product_name')->label('Item')->limit(30),
                Tables\Columns\TextColumn::make('basis')->label('Based on')
                    ->formatStateUsing(fn (string $state, AffiliateCommission $r) => $r->calculation()),
                Tables\Columns\TextColumn::make('commission_amount')->label('Commission')->money('AED')->summarize($sum()),
                Tables\Columns\TextColumn::make('bonus_amount')->label('Level / booster bonus')->money('AED')->summarize($sum())->toggleable()
                    ->description(fn (AffiliateCommission $r) => $r->bonus_note),
                Tables\Columns\TextColumn::make('markup_amount')->label('White-page mark-up')->money('AED')->summarize($sum())->toggleable(),
                Tables\Columns\TextColumn::make('coupon_deduction')->label('− Customer discount (their code)')->money('AED')->summarize($sum())->toggleable()
                    ->color(fn ($state) => (float) $state > 0 ? 'danger' : null),
                Tables\Columns\TextColumn::make('amount')->label('Total')->money('AED')->weight('bold')->summarize($sum()),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => AffiliateCommission::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) { 'available' => 'success', 'void' => 'danger', default => 'gray' }),
                Tables\Columns\TextColumn::make('available_at')->label('Earned on')->date('d M Y')->placeholder('—')->toggleable(),
            ])))
            ->filters(array_values(array_filter([
                $withAffiliate ? Tables\Filters\SelectFilter::make('affiliate_id')->label('Affiliate')->multiple()
                    ->options(fn () => Affiliate::with('user')->get()->mapWithKeys(fn ($a) => [$a->id => $a->code.' — '.$a->user?->name])) : null,
                Tables\Filters\SelectFilter::make('status')->options(AffiliateCommission::STATUSES)->multiple(),
                Tables\Filters\SelectFilter::make('basis')->label('Item type')->options(['making' => 'By-weight (% of making)', 'gram' => 'By-weight (AED per gram)', 'total' => 'Fixed price (% of total)', 'piece' => 'Per piece', 'team' => 'Team (recruits\' sales)']),
                Tables\Filters\Filter::make('created_at')->form([Forms\Components\DatePicker::make('from'), Forms\Components\DatePicker::make('until')])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
                        ->when($data['until'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))),
            ])))
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAffiliateCommissions::route('/')];
    }
}
