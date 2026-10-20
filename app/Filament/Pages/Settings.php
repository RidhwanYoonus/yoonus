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

    public static function canAccess(): bool
    {
        return \App\Support\StaffAccess::current('settings', 'edit');
    }

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
                    $text('store_name')->required(), $text('store_name_ar', 'Store name (Arabic)')->extraInputAttributes(['dir' => 'rtl'])->hintAction(\App\Filament\Support\ArabicField::translate('store_name', 'store_name_ar')),
                    $text('store_legal_name', 'Legal name (on invoices)')->required(),
                    $text('store_trn', 'TRN')->required()->length(15)->numeric()->helperText('15-digit UAE Tax Registration Number, printed on every tax invoice.'),
                    $text('store_address', 'Address')->required()->columnSpanFull(),
                    $text('store_phone', 'Phone'), $text('store_email', 'Email')->email(),
                    $text('store_caption', 'Caption (English)')->helperText('Shown under the logo and on the homepage, e.g. "Order direct from the factory · Collect from our premium retail store".')->columnSpanFull(),
                    $text('store_caption_ar', 'Caption (Arabic)')->extraInputAttributes(['dir' => 'rtl'])->hintAction(\App\Filament\Support\ArabicField::translate('store_caption', 'store_caption_ar'))->columnSpanFull(),
                    Forms\Components\Toggle::make('making_badge')->label('Show making-charge badge on products')->inline(false),
                    Forms\Components\TextInput::make('making_badge_highlight_below')->label('Highlight as "Only X% making" at or below')->numeric()->suffix('%'),
                    $text('whatsapp_number', 'WhatsApp number')->helperText('International format, e.g. 971501234567'),
                    Forms\Components\Toggle::make('whatsapp_button')->label('Floating WhatsApp button')->inline(false),
                    Forms\Components\Section::make('Arabic translation (the "Translate from English" buttons)')->columns(3)->collapsible()->schema([
                        Forms\Components\Select::make('translation_provider')->label('Translate with')->live()
                            ->options(['auto' => 'Automatic (best service with a key, else free)', 'mymemory' => 'Free (MyMemory, no account)', 'google' => 'Google Translate (API key)', 'deepl' => 'DeepL (API key, best quality)']),
                        $secret('google_translate_key', 'Google Cloud Translation API key')->visible(fn (Forms\Get $get) => in_array($get('translation_provider'), ['auto', 'google'], true)),
                        $secret('deepl_key', 'DeepL API key')->visible(fn (Forms\Get $get) => in_array($get('translation_provider'), ['auto', 'deepl'], true)),
                        Forms\Components\Textarea::make('translation_glossary')->label('My words (always translated this way)')->rows(5)->columnSpanFull()
                            ->placeholder("Kada = كادا\nJhumka = جمكا\nTwisted Bangle Pair = زوج أساور ملتوية")
                            ->helperText('One per line: English = Arabic. Used before the translator, so names and shop words are always right. Common jewellery words (rings, necklaces, bangles, earrings, pendants, sets, gold, diamond…) are already built in.'),
                    ]),
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
                    Forms\Components\Section::make('Gold price hold — time to pay')->icon('heroicon-o-clock')->columns(2)->columnSpanFull()
                        ->description('How long an online order keeps its gold price. If the customer has not paid by then, the order is cancelled automatically, the stock is released and no invoice is made. They must order again at the current gold price. (Cash on delivery is not affected.)')
                        ->schema([
                            Forms\Components\Select::make('payment_window_minutes')->label('Customer must pay within')->required()->native(false)->selectablePlaceholder(false)
                                ->options([10 => '10 minutes', 15 => '15 minutes', 20 => '20 minutes', 30 => '30 minutes (recommended)', 45 => '45 minutes', 60 => '1 hour', 90 => '1 hour 30 minutes', 120 => '2 hours', 180 => '3 hours', 240 => '4 hours'])
                                ->helperText('Shorter = less risk when the gold rate moves. Stripe keeps its card page open for at least 30 minutes.'),
                            Forms\Components\Placeholder::make('payment_window_now')->label('Waiting for payment right now')
                                ->content(fn () => rescue(fn () => \App\Models\Order::where('status', 'pending')->where('payment_status', 'unpaid')->where('payment_method', '!=', 'cod')->count().' order(s)', '—', false)),
                        ]),
                    Forms\Components\Select::make('card_gateway')->options(['mock' => 'Test mode (simulated)', 'mettpay' => 'Mettpay (PayBy)', 'telr' => 'Telr', 'stripe' => 'Stripe'])->required()->live()
                        ->helperText('Mettpay and Stripe keys go in the section below. Telr keys are set in the server .env file.'),
                    Forms\Components\Section::make('Mettpay (PayBy) card payments')->columns(2)->columnSpanFull()
                        ->visible(fn (Forms\Get $get) => $get('card_gateway') === 'mettpay')
                        ->description('From your Mettpay merchant portal. Test card: 5123 4500 0000 0008 · 05/31 · CVV 100.')
                        ->schema([
                            $text('mettpay_merchant_key', 'Merchant key')->placeholder('live-P762B55IY')->helperText('Enter it exactly as Mettpay gave it, including "live-" (or "test-") at the start.'),
                            $secret('mettpay_merchant_secret', 'Merchant secret'),
                            $text('mettpay_api_url', 'Create-order URL')->url()->placeholder(\App\Payments\MettpayGateway::DEFAULT_URL),
                            $text('mettpay_status_url', 'Payment status URL (optional)')->url()
                                ->helperText('Ask Mettpay for their payment-status (or "query order") API. With it, paid orders are confirmed automatically; without it, staff confirm each payment in the Mettpay dashboard. Use {id} where the order number goes.'),
                        ]),
                    Forms\Components\Section::make('Stripe card payments')->columns(2)->columnSpanFull()
                        ->visible(fn (Forms\Get $get) => $get('card_gateway') === 'stripe')
                        ->description(new \Illuminate\Support\HtmlString('From <a href="https://dashboard.stripe.com/apikeys" target="_blank" class="underline">dashboard.stripe.com → Developers → API keys</a>. Use the test keys (sk_test_…) first; test card 4242 4242 4242 4242, any future date, any CVC.'))
                        ->schema([
                            $secret('stripe_secret_key', 'Secret key')->placeholder('sk_test_… or sk_live_…')
                                ->helperText('The "Secret key" (starts with sk_), not the publishable key (pk_).'),
                            $secret('stripe_webhook_secret', 'Webhook signing secret (optional)')->placeholder('whsec_…')
                                ->helperText(fn () => 'Recommended once the site is online: in Stripe → Developers → Webhooks add '.route('payments.webhook', 'stripe').' with the event checkout.session.completed, then paste its signing secret here.'),
                        ]),
                    Forms\Components\Toggle::make('applepay_enabled')->label('Apple Pay')->inline(false)
                        ->helperText('Shown on iPhone, iPad and Mac (Safari). Turn Apple Pay on in your Telr / Stripe account too.'),
                    Forms\Components\Toggle::make('googlepay_enabled')->label('Google Pay')->inline(false)
                        ->helperText('Turn Google Pay on in your Telr / Stripe account too. Both use the card fee below.'),
                    Forms\Components\Placeholder::make('wallet_hint')->hiddenLabel()->content(''),
                    Forms\Components\Toggle::make('cod_enabled')->label('Cash on delivery')->inline(false),
                    Forms\Components\TextInput::make('cod_max_total')->label('COD max order')->numeric()->prefix('AED'),
                    Forms\Components\Toggle::make('tabby_enabled')->label('Tabby')->inline(false),
                    Forms\Components\TextInput::make('tabby_min')->numeric()->prefix('AED'), Forms\Components\TextInput::make('tabby_max')->numeric()->prefix('AED'),
                    Forms\Components\Toggle::make('tamara_enabled')->label('Tamara')->inline(false),
                    Forms\Components\TextInput::make('tamara_min')->numeric()->prefix('AED'), Forms\Components\TextInput::make('tamara_max')->numeric()->prefix('AED'),
                    Forms\Components\Section::make('Payment / card fee charged to the customer')
                        ->description('Added at checkout for the method the customer picks: % of the amount paid + a fixed amount. 5% VAT is added on the fee too, and it is shown on the tax invoice.')
                        ->columns(4)->columnSpanFull()->schema([
                            Forms\Components\TextInput::make('card_fee_percent')->label('Card fee %')->numeric()->minValue(0)->suffix('%'),
                            Forms\Components\TextInput::make('card_fee_fixed')->label('Card fee fixed')->numeric()->minValue(0)->prefix('AED'),
                            Forms\Components\TextInput::make('tabby_fee_percent')->label('Tabby fee %')->numeric()->minValue(0)->suffix('%'),
                            Forms\Components\TextInput::make('tabby_fee_fixed')->label('Tabby fee fixed')->numeric()->minValue(0)->prefix('AED'),
                            Forms\Components\TextInput::make('tamara_fee_percent')->label('Tamara fee %')->numeric()->minValue(0)->suffix('%'),
                            Forms\Components\TextInput::make('tamara_fee_fixed')->label('Tamara fee fixed')->numeric()->minValue(0)->prefix('AED'),
                            Forms\Components\TextInput::make('cod_fee_percent')->label('Cash on delivery fee %')->numeric()->minValue(0)->suffix('%'),
                            Forms\Components\TextInput::make('cod_fee_fixed')->label('Cash on delivery fee fixed')->numeric()->minValue(0)->prefix('AED'),
                        ]),
                ]),
                Forms\Components\Tabs\Tab::make('Delivery & pickup')->icon('heroicon-o-truck')->columns(2)->schema([
                    Forms\Components\Toggle::make('delivery_enabled')->label('Home delivery available')->inline(false)->live()
                        ->helperText('Switch off to pause delivery (holidays, busy days). Customers can then only collect from the store.'),
                    Forms\Components\Toggle::make('pickup_enabled')->label('Store pickup available')->inline(false),
                    $text('delivery_off_message', 'Message while delivery is paused (English)')->columnSpanFull()->visible(fn (Forms\Get $get) => ! $get('delivery_enabled')),
                    $text('delivery_off_message_ar', 'Message while delivery is paused (Arabic)')->extraInputAttributes(['dir' => 'rtl'])->hintAction(\App\Filament\Support\ArabicField::translate('delivery_off_message', 'delivery_off_message_ar'))->columnSpanFull()->visible(fn (Forms\Get $get) => ! $get('delivery_enabled')),
                    Forms\Components\Select::make('id_verification')->label('Proof of ID at checkout')->required()
                        ->options(['delivery' => 'Required for home delivery', 'all' => 'Required for every order (delivery and pickup)', 'off' => 'Not required'])
                        ->helperText('Customers enter their ID type, number and expiry and upload a photo as the last step before payment. They can save it for next time.'),
                    Forms\Components\Toggle::make('handover_otp')->label('Handover code on delivery / collection')->inline(false)
                        ->helperText('A 6-digit code is emailed and WhatsApped to the customer when the order is shipped (or ready for pickup). Staff must enter it to mark the order delivered.'),
                    Forms\Components\Toggle::make('product_store_map')->label('Show store map on every product page')->inline(false)
                        ->helperText('Google Maps location of each pickup branch. Set the pin under Settings → Branches & pickup.'),
                ]),
                Forms\Components\Tabs\Tab::make('Login & verification')->icon('heroicon-o-key')->columns(2)->schema([
                    Forms\Components\Toggle::make('phone_verification')->label('Customers must verify their mobile before ordering')->inline(false)->columnSpanFull()
                        ->helperText('A 6-digit code is sent on WhatsApp at the final checkout step (and after signing up). Until WhatsApp is set up, the code is shown on screen for testing.'),
                    Forms\Components\Section::make('Continue with Google')->columns(2)
                        ->description(fn () => 'Google Cloud Console → APIs & Services → Credentials → Create OAuth client ID (Web application). Authorised redirect URI: '.route('google.callback'))
                        ->schema([
                            $text('google_client_id', 'Client ID')->placeholder('…apps.googleusercontent.com'),
                            $secret('google_client_secret', 'Client secret'),
                        ]),
                ]),
                Forms\Components\Tabs\Tab::make('Security')->icon('heroicon-o-shield-check')->columns(2)
                    ->visible(fn () => (bool) auth()->user()?->is_admin)->schema([
                    Forms\Components\Toggle::make('admin_two_factor')->label('Two-step sign-in for admin & staff')->inline(false)
                        ->helperText('After the password, a 6-digit code is emailed (and WhatsApped if the staff member has a mobile number). Press "Send test email" first so you are not locked out. Locked out? Run: php artisan shop:two-factor off'),
                    Forms\Components\TextInput::make('admin_idle_minutes')->label('Sign out admin after inactivity')->numeric()->minValue(0)->maxValue(720)->suffix('minutes')
                        ->helperText('0 = never. 15–30 minutes is recommended, especially on a shared shop computer.'),
                    Forms\Components\Placeholder::make('security_notes')->hiddenLabel()->columnSpanFull()->content(new \Illuminate\Support\HtmlString(
                        '<ul class="list-disc ps-5 text-sm text-gray-600 dark:text-gray-400 space-y-1">'
                        .'<li>Every sign-in, wrong password and blocked attempt is recorded in <strong>Settings → Security log</strong>. Admins get an email when their account signs in from a new device.</li>'
                        .'<li>Logins are blocked for a minute after 5 wrong passwords. Customer passwords need 8+ characters with upper- and lower-case letters and a number.</li>'
                        .'<li>Card numbers never touch this website — customers type them on the Mettpay / Stripe / Telr page. ID photos are stored privately and only admins can open them.</li>'
                        .'</ul>')),
                ]),
                Forms\Components\Tabs\Tab::make('Policies')->icon('heroicon-o-document-text')->columns(3)->schema([
                    Forms\Components\Toggle::make('require_terms_acceptance')->label('Customer must tick "I agree" at checkout')->inline(false)
                        ->helperText('The date, time and IP of the agreement are saved on the order — useful proof if a customer disputes a card payment.'),
                    $text('policy_emirate', 'Emirate for courts / governing law')->placeholder('Dubai'),
                    Forms\Components\TextInput::make('policy_report_hours')->label('Hours to report a wrong / damaged item')->numeric()->minValue(1)->suffix('hours'),
                    Forms\Components\Placeholder::make('policy_help')->hiddenLabel()->columnSpanFull()->content(new \Illuminate\Support\HtmlString(
                        'Ready-made English and Arabic texts are shown automatically with your store name, address, TRN, email and phone from "Store & tax". '
                        .'To use your own wording, write it below — leave a box empty to keep the ready-made text. Pages: '
                        .collect(\App\Support\Policies::PAGES)->map(fn ($p) => '<a href="'.route('policy.show', $p['slug']).'" target="_blank" class="underline">'.e($p['en']).'</a>')->implode(' · ')
                        .'<br><strong>Please have a UAE lawyer review the final texts before going live.</strong>')),
                    ...collect(\App\Support\Policies::PAGES)->map(fn ($p, $k) => Forms\Components\Section::make($p['en'])->collapsed()->columnSpanFull()->columns(2)->schema([
                        Forms\Components\RichEditor::make("policy_{$k}_en")->label('English (optional)')->toolbarButtons(['h2', 'h3', 'bold', 'italic', 'bulletList', 'orderedList', 'link', 'undo', 'redo']),
                        Forms\Components\RichEditor::make("policy_{$k}_ar")->label('Arabic (optional)')->extraInputAttributes(['dir' => 'rtl'])->toolbarButtons(['h2', 'h3', 'bold', 'italic', 'bulletList', 'orderedList', 'link', 'undo', 'redo']),
                    ]))->values()->all(),
                ]),
                Forms\Components\Tabs\Tab::make('Video calls')->icon('heroicon-o-video-camera')->columns(2)->schema([
                    Forms\Components\Toggle::make('video_call_enabled')->label('Live video call button on products')->inline(false)
                        ->helperText('Can also be switched off per product (Products → edit → Stock & visibility).'),
                    $text('video_call_hours', 'Hours shown to customers')->placeholder('Daily 10:00 – 22:00'),
                    $text('video_call_notify_email', 'Email staff about new calls')->email()->placeholder('Store email is used when empty'),
                    $text('video_call_base_url', 'Video service address')->url()
                        ->helperText('https://meet.jit.si works free with no account (staff sign in with Google once to open the room). Use your own Jitsi server or 8x8 JaaS address for a branded service.'),
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
                Forms\Components\Tabs\Tab::make('WhatsApp')->icon('heroicon-o-chat-bubble-oval-left')->columns(2)->schema([
                    Forms\Components\Select::make('whatsapp_provider')->label('Send WhatsApp through')->live()->required()
                        ->options(['meta' => 'Meta — WhatsApp Business (Cloud API), recommended', 'twilio' => 'Twilio']),
                    Forms\Components\Toggle::make('whatsapp_send_invoice')->label('Send the tax invoice PDF on WhatsApp when an order is paid')->inline(false),
                    Forms\Components\Section::make('Meta WhatsApp Cloud API')->columns(2)->visible(fn (Forms\Get $get) => $get('whatsapp_provider') !== 'twilio')
                        ->description('From developers.facebook.com → your app → WhatsApp → API Setup. See the setup guide below.')
                        ->schema([
                            $text('meta_wa_phone_number_id', 'Phone number ID')->helperText('The long number under "From" in API Setup — not your phone number.'),
                            $secret('meta_wa_token', 'Permanent access token')->helperText('System user token from Business Settings → System users (starts with EAA…).'),
                            $text('meta_wa_template', 'Message template name')->placeholder('store_update')
                                ->helperText('Approved Utility template whose body is e.g. "{{1}}". Needed for messages to customers who have not written to you in the last 24 hours.'),
                            $text('meta_wa_invoice_template', 'Invoice template name')->placeholder('order_invoice')
                                ->helperText('Approved template with a Document header and body "{{1}}". Used to send the invoice PDF.'),
                            $text('meta_wa_template_lang', 'Template language code')->placeholder('en')->helperText('Exactly as in WhatsApp Manager, e.g. en, en_US or ar.'),
                            $text('meta_wa_api_version', 'Graph API version')->placeholder('v21.0'),
                        ]),
                    Forms\Components\Section::make('Twilio')->columns(3)->visible(fn (Forms\Get $get) => $get('whatsapp_provider') === 'twilio')->schema([
                        $text('twilio_sid', 'Account SID'), $secret('twilio_token', 'Auth token'),
                        $text('twilio_whatsapp_from', 'WhatsApp sender number'),
                    ]),
                    Forms\Components\Placeholder::make('wa_hint')->label('')->columnSpanFull()
                        ->content('Sent automatically: order updates, the paid tax invoice, handover codes, abandoned-cart reminders and gold-drop alerts. Use "Send test WhatsApp" above to check.'),
                ]),
                Forms\Components\Tabs\Tab::make('Email')->icon('heroicon-o-envelope')->columns(2)->schema([
                    Forms\Components\Select::make('mail_mailer')->label('Send email')->live()
                        ->options(['smtp' => 'Through my email provider (SMTP)', 'log' => 'Not yet — keep emails in the log (testing)'])
                        ->helperText('Works with Google Workspace / Gmail, Microsoft 365 / Outlook, Zoho, your web host\'s email, Brevo, Mailgun and others.'),
                    $text('mail_from_address', 'Send from (email address)')->email()->placeholder('orders@yourshop.ae'),
                    $text('mail_from_name', 'Sender name')->placeholder('Your Jewellery Shop'),
                    Forms\Components\Section::make('SMTP server')->columns(3)->visible(fn (Forms\Get $get) => $get('mail_mailer') === 'smtp')
                        ->description('Gmail / Google Workspace: smtp.gmail.com · 587 · TLS, password = an App password. Microsoft 365: smtp.office365.com · 587 · TLS. Zoho: smtp.zoho.com · 465 · SSL.')
                        ->schema([
                            $text('mail_host', 'SMTP host')->placeholder('smtp.gmail.com'),
                            Forms\Components\TextInput::make('mail_port')->label('Port')->numeric()->placeholder('587'),
                            Forms\Components\Select::make('mail_encryption')->label('Security')->options(['tls' => 'TLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None']),
                            $text('mail_username', 'Username')->placeholder('orders@yourshop.ae'),
                            $secret('mail_password', 'Password / app password'),
                        ]),
                    Forms\Components\Placeholder::make('mail_hint')->label('')->columnSpanFull()
                        ->content('Sent automatically: order confirmation and status emails (the tax invoice PDF is attached when paid), handover codes, newsletter confirmation, abandoned-cart reminders and gold-drop alerts.'),
                    $secret('mailchimp_api_key', 'Mailchimp API key (optional)'), $text('mailchimp_list_id', 'Mailchimp audience ID'),
                    $secret('klaviyo_api_key', 'Klaviyo private API key (optional)'), $text('klaviyo_list_id', 'Klaviyo list ID'),
                ]),
            ])->persistTabInQueryString(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('testEmail')->label('Send test email')->icon('heroicon-o-envelope')->color('gray')
                ->form([Forms\Components\TextInput::make('to')->label('Send to')->email()->required()->default(fn () => auth()->user()?->email)])
                ->modalDescription('Save your settings first. The test uses the saved email settings.')
                ->action(function (array $data) {
                    try {
                        \App\Support\MailSettings::apply();
                        \Illuminate\Support\Facades\Mail::to($data['to'])->send(new \App\Mail\ShopMessage('Test email from '.Store::get('store_name'), ['Your shop can send email. 🎉']));
                        config('mail.default') === 'log'
                            ? Notification::make()->warning()->title('Email is still in test mode')->body('Choose "Through my email provider (SMTP)" in Settings → Email and save.')->send()
                            : Notification::make()->success()->title('Test email sent to '.$data['to'])->send();
                    } catch (\Throwable $e) {
                        Notification::make()->danger()->title('Email failed')->body($e->getMessage())->persistent()->send();
                    }
                }),
            \Filament\Actions\Action::make('testMettpay')->label('Test Mettpay')->icon('heroicon-o-credit-card')->color('gray')
                ->visible(fn () => Store::get('card_gateway') === 'mettpay')
                ->requiresConfirmation()->modalHeading('Test Mettpay connection')
                ->modalDescription('Save your settings first. This creates a test payment of AED 1.00 at Mettpay and shows their exact reply. Nothing is charged unless you pay it.')
                ->action(function () {
                    try {
                        $result = \App\Payments\MettpayGateway::testConnection();
                        Notification::make()->success()->title('Mettpay accepted the test payment')
                            ->body(new \Illuminate\Support\HtmlString('Open the card page and use the test card 5123 4500 0000 0008 · 05/31 · 100:<br><a href="'.e($result['url']).'" target="_blank" class="underline font-semibold">Open Mettpay payment page →</a>'))
                            ->persistent()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->danger()->title('Mettpay test failed')->body($e->getMessage())->persistent()->send();
                    }
                }),
            \Filament\Actions\Action::make('testStripe')->label('Test Stripe')->icon('heroicon-o-credit-card')->color('gray')
                ->visible(fn () => Store::get('card_gateway') === 'stripe')
                ->modalDescription('Save your settings first. This checks your secret key with Stripe. Nothing is charged.')
                ->action(function () {
                    try {
                        $mode = \App\Payments\StripeGateway::testConnection();
                        Notification::make()->success()->title('Stripe key works ('.$mode.' mode)')
                            ->body($mode === 'test' ? 'Place an order with card 4242 4242 4242 4242, any future expiry, any CVC.' : 'Live mode: real cards will be charged.')->persistent()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->danger()->title('Stripe test failed')->body($e->getMessage())->persistent()->send();
                    }
                }),
            \Filament\Actions\Action::make('testWhatsApp')->label('Send test WhatsApp')->icon('heroicon-o-chat-bubble-oval-left')->color('gray')
                ->form([Forms\Components\TextInput::make('to')->label('Mobile number')->required()->placeholder('+971 50 123 4567')])
                ->modalDescription('Save your settings first. Tip: send "hi" from that phone to your business WhatsApp first, so the test arrives even without an approved template.')
                ->action(function (array $data) {
                    if (! \App\Messaging\WhatsApp::configured()) {
                        Notification::make()->warning()->title('WhatsApp is not set up yet')->body('Fill in Settings → WhatsApp and save.')->send();

                        return;
                    }
                    $ok = app(\App\Messaging\WhatsApp::class)->send($data['to'], 'Test message from '.Store::get('store_name').' ✅', 'test');
                    $error = \App\Models\NotificationLog::where('template', 'test')->latest('id')->value('error');
                    $ok ? Notification::make()->success()->title('Test WhatsApp sent')->send()
                        : Notification::make()->danger()->title('WhatsApp failed')->body($error)->persistent()->send();
                }),
        ];
    }

    public function save(): void
    {
        Store::set($this->form->getState());
        app(PricingService::class)->bumpVersion();
        StorefrontCache::flushPriceDependent();
        Notification::make()->success()->title('Settings saved')->send();
    }
}
