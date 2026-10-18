<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SecurityEventResource\Pages;
use App\Models\SecurityEvent;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Read-only log of sign-ins, wrong passwords, lockouts and two-step codes. Admin only. */
class SecurityEventResource extends Resource
{
    protected static ?string $model = SecurityEvent::class;

    protected static ?string $slug = 'security-log';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Security log';

    protected static ?string $pluralModelLabel = 'Security log';

    protected static ?int $navigationSort = -1;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $n = SecurityEvent::whereIn('event', ['login_failed', 'lockout', 'two_factor_failed'])->where('created_at', '>=', now()->subDay())->count();
        } catch (\Throwable) {
            return null;
        }

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Failed sign-in attempts in the last 24 hours';
    }

    public static function table(Table $table): Table
    {
        $danger = ['login_failed', 'lockout', 'two_factor_failed', 'new_device'];

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('When')->dateTime('d M Y, H:i:s')->sortable(),
                Tables\Columns\TextColumn::make('event')->badge()
                    ->formatStateUsing(fn (string $state) => SecurityEvent::LABELS[$state] ?? $state)
                    ->color(fn (string $state) => in_array($state, $danger, true) ? 'danger' : (str_starts_with($state, 'admin') || str_starts_with($state, 'two_factor') ? 'warning' : 'gray')),
                Tables\Columns\TextColumn::make('email')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('ip')->label('IP address')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('user_agent')->label('Device / browser')->limit(50)->tooltip(fn ($record) => $record->user_agent)->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')->options(SecurityEvent::LABELS),
                Tables\Filters\Filter::make('problems')->label('Only problems')->query(fn ($q) => $q->whereIn('event', $danger)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSecurityEvents::route('/')];
    }
}
