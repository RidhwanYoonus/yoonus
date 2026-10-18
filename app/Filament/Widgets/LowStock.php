<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LowStock extends TableWidget
{
    public static function canView(): bool
    {
        return \App\Support\StaffAccess::current('products');
    }

    protected static ?int $sort = 3;

    protected static ?string $heading = 'Low stock';

    public function table(Table $table): Table
    {
        return $table
            ->query(Product::query()->where('is_active', true)->whereColumn('stock_qty', '<=', 'low_stock_threshold')->with('goldCategory'))
            ->paginated([5])
            ->columns([
                Tables\Columns\TextColumn::make('name')->description(fn ($record) => $record->sku),
                Tables\Columns\TextColumn::make('goldCategory.name')->label('Karat'),
                Tables\Columns\TextColumn::make('stock_qty')->label('Stock')->color('danger')->weight('bold'),
            ])
            ->recordUrl(fn (Product $record) => ProductResource::getUrl('edit', ['record' => $record]))
            ->emptyStateHeading('All products are well stocked');
    }
}
