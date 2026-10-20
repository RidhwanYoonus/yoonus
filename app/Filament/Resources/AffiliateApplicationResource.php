<?php

namespace App\Filament\Resources;

use App\Affiliates\AffiliateService;
use App\Filament\Concerns\HasStaffArea;
use App\Mail\ShopMessage;
use App\Messaging\Messenger;
use App\Models\Affiliate;
use App\Models\AffiliateApplication;
use App\Models\User;
use App\Support\Settings;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** People who asked to join as affiliates — approve to create their account in one click. */
class AffiliateApplicationResource extends Resource
{
    use HasStaffArea;

    protected static string $staffArea = 'affiliates';

    protected static ?string $model = AffiliateApplication::class;

    protected static ?string $slug = 'affiliate-applications';

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'Affiliates';

    protected static ?string $navigationLabel = 'Applications';

    protected static ?int $navigationSort = 5;

    public static function getNavigationBadge(): ?string
    {
        $n = rescue(fn () => AffiliateApplication::where('status', 'new')->count(), 0, false);

        return $n ? (string) $n : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Date')->dateTime('d M Y'),
                Tables\Columns\TextColumn::make('name')->searchable()->description(fn (AffiliateApplication $r) => $r->email),
                Tables\Columns\TextColumn::make('phone'),
                Tables\Columns\TextColumn::make('social')->placeholder('—')->limit(30),
                Tables\Columns\TextColumn::make('referredBy.code')->label('Invited by')->badge()->color('warning')->placeholder('—'),
                Tables\Columns\TextColumn::make('message')->limit(40)->tooltip(fn ($record) => $record->message)->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => AffiliateApplication::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'approved' => 'success', 'rejected' => 'danger', default => 'warning' }),
            ])
            ->filters([Tables\Filters\SelectFilter::make('status')->options(AffiliateApplication::STATUSES)])
            ->actions([
                Tables\Actions\Action::make('approve')->label('Approve')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (AffiliateApplication $r) => $r->status === 'new' && \App\Support\StaffAccess::current('affiliates', 'create'))
                    ->requiresConfirmation()
                    ->modalDescription('Creates the affiliate with the default rates (Settings → Affiliates) and emails their sign-in details. You can change their rates afterwards.')
                    ->action(fn (AffiliateApplication $record) => self::approve($record)),
                Tables\Actions\Action::make('reject')->label('Reject')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (AffiliateApplication $r) => $r->status === 'new')->requiresConfirmation()
                    ->action(fn (AffiliateApplication $record) => $record->update(['status' => 'rejected'])),
            ]);
    }

    public static function approve(AffiliateApplication $app): ?Affiliate
    {
        if (User::where('email', $app->email)->whereHas('affiliate')->exists()) {
            Notification::make()->warning()->title('This email already has an affiliate account')->send();

            return null;
        }
        $password = Str::password(12, symbols: false);
        $affiliate = DB::transaction(function () use ($app, $password) {
            $user = User::where('email', $app->email)->first() ?? new User;
            if (! $user->exists) {
                $user->forceFill(['name' => $app->name, 'email' => $app->email, 'phone' => $app->phone, 'password' => $password, 'email_verified_at' => now()])->save();
            }
            $affiliate = Affiliate::create([
                'user_id' => $user->id, 'code' => AffiliateService::newCode($app->name), 'is_active' => true,
                'making_commission_percent' => Settings::get('affiliate_default_making_percent', 10),
                'fixed_commission_percent' => Settings::get('affiliate_default_fixed_percent', 3),
                'recruited_by_id' => $app->referred_by_id,
            ]);
            $app->update(['status' => 'approved', 'affiliate_id' => $affiliate->id]);

            return [$affiliate, $user->wasRecentlyCreated];
        });
        [$affiliate, $newUser] = $affiliate;
        $lines = ['Welcome! Your affiliate account is ready. Your code: '.$affiliate->code.'.',
            $newUser ? 'Sign in with '.$app->email.' and the password '.$password.' — please change it after signing in.' : 'Sign in with your existing account.'];
        app(Messenger::class)->email($app->email, new ShopMessage('Your affiliate account is ready', $lines, 'Open my dashboard', route('affiliate.dashboard')), 'affiliate_welcome');
        Notification::make()->success()->title('Affiliate '.$affiliate->code.' created')->body('Sign-in details were emailed to '.$app->email.'.')->send();

        return $affiliate;
    }

    public static function getPages(): array
    {
        return ['index' => AffiliateApplicationResource\Pages\ManageAffiliateApplications::route('/')];
    }
}
