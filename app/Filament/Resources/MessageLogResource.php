<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MessageLogResource\Pages;
use App\Models\NotificationLog;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Every WhatsApp and email message the shop sent (or tried to send). */
class MessageLogResource extends Resource
{
    protected static ?string $model = NotificationLog::class;

    protected static ?string $slug = 'messages-sent';

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Messages sent';

    protected static ?string $pluralModelLabel = 'Messages sent';

    public const STATUSES = ['sent' => 'Sent', 'logged' => 'Not sent (not set up)', 'failed' => 'Failed'];

    public static function canCreate(): bool
    {
        return false;
    }

    public static function templateLabel(string $template): string
    {
        $named = ['handover_otp' => 'Handover code', 'video_call_staff' => 'Video call alert (staff)', 'order_ready' => 'Order ready for pickup'];
        if (isset($named[$template])) {
            return $named[$template];
        }
        if (preg_match('/^abandoned_cart_?(\d+)$/', $template, $m)) {
            return 'Abandoned cart reminder '.$m[1];
        }

        return str($template)->replace(['order_', '_'], ['Order ', ' '])->ucfirst()->toString();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Sent')->dateTime('d M Y, H:i')->sortable()
                    ->description(fn (NotificationLog $record) => $record->created_at?->diffForHumans()),
                Tables\Columns\TextColumn::make('channel')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'whatsapp' ? 'WhatsApp' : ucfirst($state))
                    ->color(fn (string $state) => $state === 'whatsapp' ? 'success' : 'info'),
                Tables\Columns\TextColumn::make('recipient')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('template')->label('Message')->formatStateUsing(fn (string $state) => self::templateLabel($state))->searchable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => self::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => ['sent' => 'success', 'failed' => 'danger'][$state] ?? 'gray')
                    ->tooltip(fn (NotificationLog $record) => $record->error),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('channel')->options(['whatsapp' => 'WhatsApp', 'email' => 'Email']),
                Tables\Filters\SelectFilter::make('status')->options(self::STATUSES),
                Tables\Filters\SelectFilter::make('template')->label('Message type')
                    ->options(fn () => NotificationLog::query()->distinct()->orderBy('template')->pluck('template')->mapWithKeys(fn ($t) => [$t => self::templateLabel($t)])->all()),
                Tables\Filters\Filter::make('date')->form([
                    Forms\Components\DatePicker::make('from'), Forms\Components\DatePicker::make('until'),
                ])->columns(2)->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                    ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->actions([Tables\Actions\ViewAction::make()->label('Text')])
            ->emptyStateHeading('No messages yet');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\TextEntry::make('recipient'),
            Infolists\Components\TextEntry::make('status')->formatStateUsing(fn (string $state) => self::STATUSES[$state] ?? $state),
            Infolists\Components\TextEntry::make('body')->label('Message')->columnSpanFull()->prose()->placeholder('—'),
            Infolists\Components\TextEntry::make('error')->columnSpanFull()->color('danger')->visible(fn (NotificationLog $record) => (bool) $record->error),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListMessageLogs::route('/')];
    }
}
