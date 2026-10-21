<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasStaffArea;
use App\Messaging\WhatsApp;
use App\Models\StoreVisit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Customers who asked to come and see a piece (from the product page or an affiliate's page). */
class StoreVisitResource extends Resource
{
    use HasStaffArea;

    protected static string $staffArea = 'store_visits';

    protected static ?string $model = StoreVisit::class;

    protected static ?string $slug = 'store-visits';

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Store visits';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $n = rescue(fn () => StoreVisit::where('status', 'new')->count(), 0, false);

        return $n ? (string) $n : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('status')->options(StoreVisit::STATUSES)->required(),
            Forms\Components\DateTimePicker::make('preferred_at')->label('Visit time'),
            Forms\Components\Textarea::make('staff_note')->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['product', 'branch', 'affiliate']))
            ->recordClasses(fn (StoreVisit $r) => $r->affiliate_id ? 'affiliate-order-row' : null)
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Asked on')->dateTime('d M H:i')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->description(fn (StoreVisit $r) => $r->phone)
                    ->url(fn (StoreVisit $r) => WhatsApp::link($r->phone, 'Hello '.$r->name), true),
                Tables\Columns\TextColumn::make('preferred_at')->label('Wants to come')->dateTime('d M Y H:i')->placeholder('Any time'),
                Tables\Columns\TextColumn::make('product.name')->label('Piece')->placeholder('—')->limit(30),
                Tables\Columns\TextColumn::make('branch.name')->label('Store')->placeholder('—'),
                Tables\Columns\TextColumn::make('affiliate.code')->label('Affiliate')->badge()->color('warning')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => StoreVisit::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'new' => 'warning', 'confirmed' => 'info', 'visited' => 'success', default => 'gray' }),
            ])
            ->filters([Tables\Filters\SelectFilter::make('status')->options(StoreVisit::STATUSES)])
            ->actions([
                Tables\Actions\Action::make('sale')->label('Credit affiliate')->icon('heroicon-o-building-storefront')->color('warning')
                    ->visible(fn (StoreVisit $r) => $r->affiliate && \App\Support\StaffAccess::current('affiliate_commissions', 'edit'))
                    ->modalDescription('The customer bought in the shop: give the affiliate their commission.')
                    ->form(AffiliateResource::storeSaleForm())
                    ->action(function (StoreVisit $record, array $data) {
                        AffiliateResource::runStoreSale($record->affiliate, $data);
                        $record->update(['status' => 'visited']);
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => StoreVisitResource\Pages\ManageStoreVisits::route('/')];
    }
}
