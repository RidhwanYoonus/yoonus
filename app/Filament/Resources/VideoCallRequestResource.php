<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VideoCallRequestResource\Pages;
use App\Models\VideoCallRequest;
use App\Filament\Concerns\HasStaffArea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Customers waiting for a live video call. The list refreshes itself every 10 seconds. */
class VideoCallRequestResource extends Resource
{
    use HasStaffArea;

    protected static string $staffArea = 'video_calls';

    protected static ?string $model = VideoCallRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-video-camera';

    protected static ?string $navigationGroup = 'Customers';

    protected static ?string $navigationLabel = 'Live video calls';

    protected static ?int $navigationSort = -1;

    protected static ?string $pluralModelLabel = 'Live video calls';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        // Guarded so the admin still opens (and offers "Update database now") before the table exists.
        try {
            $n = VideoCallRequest::where('status', 'waiting')->where('created_at', '>=', now()->subHours(2))->count();
        } catch (\Throwable) {
            return null;
        }

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('product', 'staff'))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Requested')->since()->sortable()->description(fn (VideoCallRequest $record) => $record->created_at->format('d M, H:i')),
                Tables\Columns\TextColumn::make('name')->description(fn (VideoCallRequest $record) => $record->phone)->searchable(['name', 'phone']),
                Tables\Columns\TextColumn::make('product.name')->label('Product')->description(fn (VideoCallRequest $record) => $record->product?->sku)
                    ->url(fn (VideoCallRequest $record) => $record->product ? route('product.show', $record->product) : null, true),
                Tables\Columns\TextColumn::make('locale')->label('Lang')->badge()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => VideoCallRequest::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => ['waiting' => 'danger', 'answered' => 'success', 'done' => 'gray', 'missed' => 'warning'][$state] ?? 'gray'),
                Tables\Columns\TextColumn::make('staff.name')->label('Answered by')->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(VideoCallRequest::STATUSES),
            ])
            ->actions([
                Tables\Actions\Action::make('join')->label('Join call')->icon('heroicon-o-video-camera')->color('success')->button()
                    ->visible(fn (VideoCallRequest $record) => in_array($record->status, ['waiting', 'answered']))
                    // Marks the call answered by this staff member, then opens the video room in a new tab.
                    ->url(fn (VideoCallRequest $record) => route('admin.video-call.join', $record->id), true),
                Tables\Actions\Action::make('sendLink')->label('Send meeting link')->icon('heroicon-o-link')->color('warning')
                    ->visible(fn (VideoCallRequest $record) => in_array($record->status, ['waiting', 'answered']))
                    ->modalDescription('Paste a link for this customer only, e.g. a new Google Meet (meet.google.com → New meeting → Create a meeting for later) or your Zoom link. Their waiting page switches to it within 5 seconds, and it is also sent on WhatsApp and email.')
                    ->fillForm(fn (VideoCallRequest $record) => ['meeting_url' => $record->meeting_url])
                    ->form([\Filament\Forms\Components\TextInput::make('meeting_url')->label('Meeting link')->url()->required()->maxLength(500)->placeholder('https://meet.google.com/abc-defg-hij')])
                    ->action(function (VideoCallRequest $record, array $data) {
                        $record->update(['meeting_url' => $data['meeting_url'], 'status' => 'answered', 'staff_id' => $record->staff_id ?? auth()->id(), 'answered_at' => $record->answered_at ?? now()]);
                        $text = __('shop.video.link_message', ['store' => \App\Support\Settings::get('store_name'), 'link' => $data['meeting_url']]);
                        $messenger = app(\App\Messaging\Messenger::class);
                        $messenger->whatsapp($record->phone, $text, 'video_call_link');
                        if ($email = $record->user?->email) {
                            $messenger->email($email, new \App\Mail\ShopMessage(__('shop.video.link_subject', ['store' => \App\Support\Settings::get('store_name')]), [$text]), 'video_call_link');
                        }
                        \Filament\Notifications\Notification::make()->success()->title('Meeting link sent')->body('Now open the same link yourself and let the customer in.')->send();
                    }),
                Tables\Actions\Action::make('whatsapp')->label('WhatsApp')->icon('heroicon-o-chat-bubble-oval-left')->color('gray')
                    ->url(fn (VideoCallRequest $record) => 'https://wa.me/'.preg_replace('/\D/', '', $record->phone), true),
                Tables\Actions\Action::make('done')->label('Done')->icon('heroicon-o-check')->color('gray')
                    ->visible(fn (VideoCallRequest $record) => in_array($record->status, ['waiting', 'answered']))
                    ->action(fn (VideoCallRequest $record) => $record->update(['status' => 'done'])),
                Tables\Actions\Action::make('missed')->label('Missed')->icon('heroicon-o-phone-x-mark')->color('warning')
                    ->visible(fn (VideoCallRequest $record) => $record->status === 'waiting')
                    ->action(fn (VideoCallRequest $record) => $record->update(['status' => 'missed'])),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListVideoCallRequests::route('/')];
    }
}
