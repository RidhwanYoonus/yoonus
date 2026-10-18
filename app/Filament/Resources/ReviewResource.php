<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Review;
use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Concerns\HasStaffArea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReviewResource extends Resource
{
    use HasStaffArea;

    protected static string $staffArea = 'reviews';

    protected static ?string $model = Review::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Customers';

    protected static ?string $navigationLabel = 'Review moderation';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Review::where('status', 'pending')->count();

        return $n ? (string) $n : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('author_name')->required(),
            Forms\Components\Select::make('rating')->options([1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5])->required(),
            Forms\Components\Textarea::make('body')->required()->columnSpanFull(),
            Forms\Components\Select::make('status')->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            Tables\Columns\TextColumn::make('product.name')->limit(30),
            Tables\Columns\TextColumn::make('author_name'),
            Tables\Columns\TextColumn::make('rating')->formatStateUsing(fn ($state) => str_repeat('★', $state)),
            Tables\Columns\TextColumn::make('body')->limit(60)->tooltip(fn (Review $record) => $record->body),
            Tables\Columns\ImageColumn::make('photos')->disk('public')->circular()->stacked()->limit(3),
            Tables\Columns\IconColumn::make('verified_purchase')->boolean()->label('Verified'),
            Tables\Columns\TextColumn::make('status')->badge()->color(fn ($state) => ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'][$state]),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'])->default('pending'),
        ])->actions([
            Tables\Actions\Action::make('approve')->icon('heroicon-o-check')->color('success')->visible(fn (Review $record) => $record->status !== 'approved')->action(fn (Review $record) => $record->update(['status' => 'approved'])),
            Tables\Actions\Action::make('reject')->icon('heroicon-o-x-mark')->color('danger')->visible(fn (Review $record) => $record->status !== 'rejected')->action(fn (Review $record) => $record->update(['status' => 'rejected'])),
            Tables\Actions\EditAction::make(),
        ])->bulkActions([
            Tables\Actions\BulkAction::make('approve')->icon('heroicon-o-check')->action(fn ($records) => $records->each->update(['status' => 'approved'])),
            Tables\Actions\BulkAction::make('reject')->icon('heroicon-o-x-mark')->action(fn ($records) => $records->each->update(['status' => 'rejected'])),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageReviews::route('/')];
    }
}
