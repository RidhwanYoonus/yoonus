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
            self::affiliateCommissionAction(),
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
                    Forms\Components\DateTimePicker::make('happened_at')->label('Date and time')->seconds(false)->native(false)
                        ->default(now())->maxDate(now()->addMinutes(5))->displayFormat('d M Y, H:i')
                        ->helperText('When it happened — change it if you are entering it later (e.g. delivered yesterday). Shown in the order history.'),
                    Forms\Components\DatePicker::make('expected_at')->label(fn (Forms\Get $get) => $record->isPickup() ? 'Ready to collect on (shown to the customer)' : 'Expected delivery date (shown to the customer)')
                        ->native(false)->displayFormat('D d M Y')->default($record->expected_at ?? null)->minDate(today())
                        ->visible(fn (Forms\Get $get) => ! in_array($get('status'), ['delivered', 'cancelled', 'refunded'], true))
                        ->helperText('Optional. Replaces the automatic estimate on the customer\'s order page and in the status message.'),
                    Forms\Components\TextInput::make('note')->maxLength(255),
                ])
                ->action(function (Order $record, array $data) {
                    try {
                        if ($data['status'] === 'delivered') {
                            OrderResource::verifyHandover($record, $data);
                        }
                        $record->update(array_filter(['tracking_carrier' => $data['tracking_carrier'] ?? null, 'tracking_number' => $data['tracking_number'] ?? null]));
                        if (array_key_exists('expected_at', $data) && \Illuminate\Support\Facades\Schema::hasColumn('orders', 'expected_at')) {
                            $record->forceFill(['expected_at' => $data['expected_at'] ?: null])->save();
                        }
                        $at = filled($data['happened_at'] ?? null) ? \Illuminate\Support\Carbon::parse($data['happened_at']) : null;
                        app(OrderService::class)->transition($record, $data['status'], Auth::user(), $data['note'] ?? null, $at);
                        Notification::make()->success()->title('Status updated')->send();
                        $this->refreshFormData([]);
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
            Actions\Action::make('storefront')->label('Customer view')->color('gray')->url(fn (Order $record) => route('order.show', $record->number))->openUrlInNewTab(),
        ];
    }

    /**
     * Fix the affiliate side of an order after a mistake: change or remove the affiliate, or correct each
     * line's commission / mark-up / bonus. The customer's price is not changed. Every change is noted in the order history.
     */
    public static function affiliateCommissionAction(): Actions\Action
    {
        $money = fn (string $name, string $label) => Forms\Components\TextInput::make($name)->label($label)->numeric()->minValue(0)->prefix('AED')->required();

        return Actions\Action::make('affiliateCommission')->label('Affiliate commission')->icon('heroicon-o-user-plus')->color('warning')
            ->visible(fn (Order $record) => \App\Support\StaffAccess::current('affiliate_commissions', 'edit')
                && ($record->affiliate_id || $record->affiliateCommissions()->exists()))
            ->modalHeading('Edit the affiliate commission of this order')
            ->modalDescription('Use this to fix a mistake. The customer\'s price and invoice do not change; only what the affiliate earns. Lines already collected by the affiliate cannot be changed here — use an adjustment in the Commission report.')
            ->fillForm(fn (Order $record) => [
                'affiliate_id' => $record->affiliate_id,
                'lines' => $record->affiliateCommissions()->with('affiliate')->get()->map(fn ($c) => [
                    'id' => $c->id, 'label' => ($c->affiliate?->code ?? '?').' · '.($c->product_name ?? 'Recruiter share').' · '.$c->status,
                    'commission_amount' => $c->commission_amount, 'markup_amount' => $c->markup_amount, 'bonus_amount' => $c->bonus_amount ?? 0,
                    'locked' => $c->status === 'paid',
                ])->all(),
            ])
            ->form([
                Forms\Components\Select::make('affiliate_id')->label('Affiliate on this order')->searchable()->preload()
                    ->options(fn () => \App\Models\Affiliate::with('user')->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => $a->code.' — '.($a->user?->name ?? '')]))
                    ->placeholder('No affiliate (remove — commission becomes void)')
                    ->helperText('Changing it moves the commission lines to the chosen affiliate.'),
                Forms\Components\Repeater::make('lines')->label('Commission lines')->addable(false)->deletable(false)->reorderable(false)->columns(3)
                    ->itemLabel(fn (array $state) => $state['label'] ?? null)
                    ->schema([
                        Forms\Components\Hidden::make('id'), Forms\Components\Hidden::make('label'), Forms\Components\Hidden::make('locked'),
                        $money('commission_amount', 'Commission')->disabled(fn (Forms\Get $get) => (bool) $get('locked')),
                        $money('markup_amount', 'Mark-up (white page)')->disabled(fn (Forms\Get $get) => (bool) $get('locked')),
                        $money('bonus_amount', 'Level / booster bonus')->disabled(fn (Forms\Get $get) => (bool) $get('locked')),
                    ]),
                Forms\Components\Textarea::make('reason')->label('Reason (kept in the order history)')->required()->rows(2)->maxLength(300),
            ])
            ->action(function (Order $record, array $data) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($record, $data) {
                    $rows = $record->affiliateCommissions()->get()->keyBy('id');
                    $before = (float) $rows->sum('amount');
                    $newAffiliate = $data['affiliate_id'] ?: null;
                    $mainBefore = $record->affiliate_id;
                    foreach ($data['lines'] ?? [] as $line) {
                        $c = $rows->get($line['id'] ?? 0);
                        if (! $c || $c->status === 'paid') {
                            continue;
                        }
                        $commission = (float) ($line['commission_amount'] ?? $c->commission_amount);
                        $markup = (float) ($line['markup_amount'] ?? $c->markup_amount);
                        $bonus = (float) ($line['bonus_amount'] ?? $c->bonus_amount ?? 0);
                        $c->fill(['commission_amount' => $commission, 'markup_amount' => $markup,
                            ...(\Illuminate\Support\Facades\Schema::hasColumn('affiliate_commissions', 'bonus_amount') ? ['bonus_amount' => $bonus] : []),
                            'amount' => max(0, $commission + $markup + $bonus - (float) ($c->coupon_deduction ?? 0))]);
                        // The order's own lines follow the affiliate chosen above; a recruiter's share stays with the recruiter.
                        if ($c->affiliate_id === $mainBefore) {
                            $newAffiliate ? $c->affiliate_id = $newAffiliate : $c->status = 'void';
                        }
                        $c->save();
                    }
                    $record->forceFill(['affiliate_id' => $newAffiliate])->save();
                    if (! $newAffiliate) {
                        $record->forceFill(['affiliate_white_page' => false])->save();
                    }
                    $after = (float) $record->affiliateCommissions()->where('status', '!=', 'void')->sum('amount');
                    $who = fn ($id) => $id ? (\App\Models\Affiliate::find($id)?->code ?? '#'.$id) : 'none';
                    app(OrderService::class)->history($record, $record->status, $record->status, Auth::user(),
                        'Affiliate commission edited ('.$who($mainBefore).' → '.$who($newAffiliate).', AED '.number_format($before, 2).' → AED '.number_format($after, 2).'): '.$data['reason']);
                    app(\App\Affiliates\AffiliateService::class)->sync($record->fresh());
                });
                Notification::make()->success()->title('Affiliate commission updated')->send();
            });
    }
}
