<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Shop\HandoverOtp;
use App\Shop\OrderService;
use App\Support\IdDocument;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OrderResource::invoiceAction(),
            Actions\Action::make('activity')->label('Customer activity')->icon('heroicon-o-finger-print')->color('gray')
                ->visible(fn () => \App\Support\StaffAccess::current('activity'))
                ->url(fn () => \App\Filament\Resources\ActivityLogResource::urlFor($this->record->user_id, $this->record->user_id ? null : $this->record->email)),
            Actions\Action::make('undoStatus')->label('Undo last status')->icon('heroicon-o-arrow-uturn-left')->color('gray')
                ->visible(fn (Order $record) => (bool) Auth::user()?->is_admin && app(OrderService::class)->revertTarget($record))
                ->modalHeading('Undo last status change')
                ->modalDescription(fn (Order $record) => 'Move this order back from "'.$record->statusLabel().'" to "'.$record->statusLabel(app(OrderService::class)->revertTarget($record)).'". '
                    .match ($record->status) {
                        'cancelled' => 'The stock is taken again. ',
                        'delivered' => 'The handover must be confirmed again (send a new code), sold-out pieces show in the shop again'.($record->payment_method === 'cod' ? ' and the cash payment is removed' : '').'. ',
                        default => '',
                    }.'The customer is not notified. This is recorded in the status history.')
                ->form([\Filament\Forms\Components\TextInput::make('reason')->label('Why? (kept in the history)')->required()->maxLength(191)])
                ->action(function (Order $record, array $data) {
                    try {
                        app(OrderService::class)->revertStatus($record, Auth::user(), $data['reason']);
                        \Filament\Notifications\Notification::make()->success()->title('Status changed back')->send();
                    } catch (\RuntimeException $e) {
                        \Filament\Notifications\Notification::make()->danger()->title('Not changed')->body($e->getMessage())->send();
                    }
                    $this->redirect(OrderResource::getUrl('view', ['record' => $record]));
                }),
            Actions\Action::make('handover')->label(fn (Order $record) => $record->isPickup() ? 'Customer collected' : 'Hand over to customer')
                ->icon('heroicon-o-shield-check')->color('success')
                ->visible(fn (Order $record) => $record->canTransitionTo('delivered') && \App\Support\StaffAccess::current('orders', 'edit'))
                ->modalDescription(fn (Order $record) => $record->hasIdDocument() ? 'Check the ID first: '.IdDocument::masked($record->id_type, $record->id_last4).' — '.$record->customer_name : 'Ask the customer for the 6-digit code we sent them.')
                ->form(fn (Order $record) => OrderResource::handoverFields($record, fn () => true))
                ->action(function (Order $record, array $data) {
                    try {
                        OrderResource::verifyHandover($record, $data);
                        app(OrderService::class)->transition($record, 'delivered', Auth::user(), $record->handover_verified_via === 'override' ? 'Handover override' : 'Handover verified by code');
                        Notification::make()->success()->title($record->isPickup() ? 'Collected' : 'Delivered')->send();
                        $this->refreshFormData([]);
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title($e->getMessage())->persistent()->send();
                    }
                }),
            Actions\Action::make('sendCode')->label(fn (Order $record) => $record->handover_otp_sent_at ? 'Resend handover code' : 'Send handover code')
                ->icon('heroicon-o-key')->color('gray')->requiresConfirmation()
                ->modalDescription(fn (Order $record) => 'A new 6-digit code goes to '.$record->email.' and WhatsApp '.$record->phone.'. Any earlier code stops working.')
                ->visible(fn (Order $record) => HandoverOtp::required($record) && in_array($record->status, ['packed', 'shipped', 'ready']) && \App\Support\StaffAccess::current('orders', 'edit'))
                ->action(function (Order $record) {
                    app(HandoverOtp::class)->issue($record);
                    Notification::make()->success()->title('Handover code sent')->send();
                }),
            Actions\Action::make('advance')->label('Update status')->icon('heroicon-o-arrow-right-circle')
                ->visible(fn (Order $record) => $record->allowedTransitions() !== [] && \App\Support\StaffAccess::current('orders', 'edit'))
                ->form(fn (Order $record) => [
                    Forms\Components\Select::make('status')->required()->live()
                        ->options(collect($record->allowedTransitions())->mapWithKeys(fn ($s) => [$s => $record->statusLabel($s)])),
                    ...OrderResource::handoverFields($record, fn (Forms\Get $get) => $get('status') === 'delivered'),
                    Forms\Components\TextInput::make('tracking_carrier')->default($record->tracking_carrier)->visible(! $record->isPickup()),
                    Forms\Components\TextInput::make('tracking_number')->default($record->tracking_number)->visible(! $record->isPickup()),
                    Forms\Components\TextInput::make('note')->maxLength(255),
                ])
                ->action(function (Order $record, array $data) {
                    try {
                        if ($data['status'] === 'delivered') {
                            OrderResource::verifyHandover($record, $data);
                        }
                        $record->update(array_filter(['tracking_carrier' => $data['tracking_carrier'] ?? null, 'tracking_number' => $data['tracking_number'] ?? null]));
                        app(OrderService::class)->transition($record, $data['status'], Auth::user(), $data['note'] ?? null);
                        Notification::make()->success()->title('Status updated')->send();
                        $this->refreshFormData([]);
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
            Actions\Action::make('storefront')->label('Customer view')->color('gray')->url(fn (Order $record) => route('order.show', $record->number))->openUrlInNewTab(),
        ];
    }
}
