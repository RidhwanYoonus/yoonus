<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Shop\OrderService;
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
            Actions\Action::make('advance')->label('Update status')->icon('heroicon-o-arrow-right-circle')
                ->visible(fn (Order $record) => ! empty(Order::TRANSITIONS[$record->status]))
                ->form(fn (Order $record) => [
                    Forms\Components\Select::make('status')->required()->options(collect(Order::TRANSITIONS[$record->status])
                        ->when($record->payment_method === 'cod' && $record->status === 'pending', fn ($options) => $options->push('packed'))
                        ->mapWithKeys(fn ($s) => [$s => ucfirst($s)])),
                    Forms\Components\TextInput::make('tracking_carrier')->default($record->tracking_carrier),
                    Forms\Components\TextInput::make('tracking_number')->default($record->tracking_number),
                    Forms\Components\TextInput::make('note')->maxLength(255),
                ])
                ->action(function (Order $record, array $data) {
                    try {
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
