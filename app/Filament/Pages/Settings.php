<?php

namespace App\Filament\Pages;

use App\Pricing\PricingService;
use App\Pricing\RoundingMode;
use App\Support\Settings as Store;
use App\Support\StorefrontCache;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/** Everything the shop owner configures without code edits: store/TRN, pricing, payments, pixels, integrations. */
class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = -1;

    protected static string $view = 'filament.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(Store::all());
    }

    public function form(Form $form): Form
    {
        $text = fn (string $k, ?string $label = null) => Forms\Components\TextInput::make($k)->label($label ?? str($k)->replace('_', ' ')->ucfirst());
        $secret = fn (string $k, string $label) => Forms\Components\TextInput::make($k)->label($label)->password()->revealable();

        return $form->statePath('data')->schema([
            Forms\Components\Tabs::make()->tabs([
                Forms\Components\Tabs\Tab::make('Store & tax')->icon('heroicon-o-building-office')->columns(2)->schema([
                    $text('store_name')->required(), $text('store_name_ar', 'Store name (Arabic)')->extraInputAttributes(['dir' => 'rtl']),
                    $text('store_legal_name', 'Legal name (on invoices)')->required(),
                    $text('store_trn', 'TRN')->required()->length(15)->numeric()->helperText('15-digit UAE Tax Registration Number, printed on every tax invoice.'),
                    $text('store_address', 'Address')->required()->columnSpanFull(),
                    $text('store_phone', 'Phone'), $text('store_email', 'Email')->email(),
                    $text('whatsapp_number', 'WhatsApp number')->helperText('International format, e.g. 971501234567'),
                    Forms\Components\Toggle::make('whatsapp_button')->label('Floating WhatsApp button')->inline(false),
                ]),
                Forms\Components\Tabs\Tab::make('Pricing')->icon('heroicon-o-calculator')->columns(2)->schema([
                    Forms\Components\TextInput::make('default_vat_percent')->label('Default VAT %')->numeric()->required()->suffix('%'),
                    Forms\Components\Select::make('rounding_mode')->options(collect(RoundingMode::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()]))->required(),
                    Forms\Components\TextInput::make('rate_stale_hours')->label('Warn when gold rate is older than')->numeric()->suffix('hours')->required(),
                    Forms\Components\Toggle::make('block_checkout_when_stale')->label('Block checkout while the rate is stale')->inline(false),
                    $text('gold_rate_source_url', 'Gold rate website (Fetch button)')->url()->helperText('Page the "Fetch rate" button reads, e.g. https://dubaicityofgold.com/'),
                    $text('gold_rate_api_url', 'Gold rate API URL (optional)')->url()->helperText('Leave empty to enter rates manually. Polled every 15 minutes.'),
                    $secret('gold_rate_api_key', 'Gold rate API key'),
                ]),
                Forms\Components\Tabs\Tab::make('Payments')->icon('heroicon-o-credit-card')->columns(3)->schema([
                    Forms\Components\Select::make('card_gateway')->options(['mock' => 'Test mode (simulated)', 'telr' => 'Telr', 'stripe' => 'Stripe'])->required()
                        ->helperText('API keys are set in the server .env file.'),
                    Forms\Components\Toggle::make('cod_enabled')->label('Cash on delivery')->inline(false),
                    Forms\Components\TextInput::make('cod_max_total')->label('COD max order')->numeric()->prefix('AED'),
                    Forms\Components\Toggle::make('tabby_enabled')->label('Tabby')->inline(false),
                    Forms\Components\TextInput::make('tabby_min')->numeric()->prefix('AED'), Forms\Components\TextInput::make('tabby_max')->numeric()->prefix('AED'),
                    Forms\Components\Toggle::make('tamara_enabled')->label('Tamara')->inline(false),
                    Forms\Components\TextInput::make('tamara_min')->numeric()->prefix('AED'), Forms\Components\TextInput::make('tamara_max')->numeric()->prefix('AED'),
                    Forms\Components\Toggle::make('pickup_enabled')->label('Store pickup')->inline(false),
                ]),
                Forms\Components\Tabs\Tab::make('Loyalty & referrals')->icon('heroicon-o-star')->columns(2)->schema([
                    Forms\Components\TextInput::make('loyalty_earn_per_aed')->label('Spend per point')->numeric()->prefix('AED')->helperText('1 point per AED X spent'),
                    Forms\Components\TextInput::make('loyalty_point_value')->label('Value of 1 point')->numeric()->prefix('AED'),
                    Forms\Components\TextInput::make('loyalty_min_redeem')->label('Minimum points to redeem')->numeric(),
                    Forms\Components\TextInput::make('referral_reward')->label('Referral reward (each side)')->numeric()->prefix('AED'),
                ]),
                Forms\Components\Tabs\Tab::make('Marketing pixels')->icon('heroicon-o-chart-bar')->columns(2)->schema([
                    $text('gtm_id', 'Google Tag Manager ID')->placeholder('GTM-XXXXXXX'),
                    $text('ga4_id', 'Google Analytics 4 ID')->placeholder('G-XXXXXXXXXX'),
                    $secret('ga4_api_secret', 'GA4 Measurement Protocol secret (server-side purchases)'),
                    $text('meta_pixel_id', 'Meta Pixel ID'),
                    $secret('meta_capi_token', 'Meta Conversions API token'),
                    $text('tiktok_pixel_id', 'TikTok Pixel ID'),
                    $text('snap_pixel_id', 'Snapchat Pixel ID'),
                    $text('onesignal_app_id', 'OneSignal App ID (web push)'),
                    $secret('onesignal_api_key', 'OneSignal REST API key'),
                ]),
                Forms\Components\Tabs\Tab::make('Messaging')->icon('heroicon-o-chat-bubble-oval-left')->columns(2)->schema([
                    $text('twilio_sid', 'Twilio Account SID'), $secret('twilio_token', 'Twilio Auth token'),
                    $text('twilio_whatsapp_from', 'WhatsApp sender number')->helperText('Approved WhatsApp Business number on Twilio. Without it, messages are logged only.'),
                    Forms\Components\Placeholder::make('wa_hint')->label('')->content('Abandoned-cart (1h/24h/72h), order status and gold-drop messages use this sender.'),
                    $secret('mailchimp_api_key', 'Mailchimp API key'), $text('mailchimp_list_id', 'Mailchimp audience ID'),
                    $secret('klaviyo_api_key', 'Klaviyo private API key'), $text('klaviyo_list_id', 'Klaviyo list ID'),
                ]),
            ])->persistTabInQueryString(),
        ]);
    }

    public function save(): void
    {
        Store::set($this->form->getState());
        app(PricingService::class)->bumpVersion();
        StorefrontCache::flushPriceDependent();
        Notification::make()->success()->title('Settings saved')->send();
    }
}
