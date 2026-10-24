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
                    Forms\Components\Toggle::make('seo_auto_fill')->label('Write SEO automatically')->inline(false)
                        ->helperText('When a product or category is saved with empty SEO boxes, they are written from its details (name, karat, weight, diamond, lab…) in English and Arabic. Text you type is never changed.'),
                    Forms\Components\Toggle::make('product_text_style')->label('Tidy product names and descriptions')->inline(false)
                        ->helperText('On save, English names get title case ("GOLD RING WITH diamond 18k" → "Gold Ring with Diamond 18K") and descriptions get a capital at the start of each sentence. GIA, IGI, VVS1, CZ… stay in capitals.'),
                    Forms\Components\Toggle::make('shop_hide_sold')->label('Hide sold pieces in shop lists')->inline(false)
                        ->helperText('Shop and category pages start with "Available only" ticked; customers can untick it to see sold pieces too.'),
                    Forms\Components\Toggle::make('making_watermark')->label('Stamp low making charge across the photo')->inline(false)
                        ->helperText('A soft gold stamp such as "ONLY 3% MAKING" over the photo of pieces in stock below the limit. Sold pieces show "Sold" instead.'),
                    Forms\Components\TextInput::make('making_watermark_below')->label('Stamp when the making charge is below')->numeric()->suffix('%')->placeholder('5'),
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
                Forms\Components\Tabs\Tab::make('Home page')->icon('heroicon-o-home')->columns(2)->schema([
                    Forms\Components\Placeholder::make('home_help')->hiddenLabel()->columnSpanFull()
                        ->content('The big banner at the top of the home page and the four small boxes under it. Leave a box empty to keep the built-in wording (shown in grey).'),
                    $text('home_eyebrow', 'Small line above the headline')->placeholder('Fine gold jewellery · UAE'),
                    $text('home_eyebrow_ar', 'Small line (Arabic)')->extraInputAttributes(['dir' => 'rtl']),
                    $text('home_headline', 'Headline')->placeholder('Gold, priced honestly. Crafted beautifully.'),
                    $text('home_headline_ar', 'Headline (Arabic)')->extraInputAttributes(['dir' => 'rtl']),
                    Forms\Components\Textarea::make('home_subline')->label('Text under the headline')->rows(2)->placeholder("Every piece is priced live from today's gold rate, with the making charge and 5% VAT shown line by line — no hidden margins."),
                    Forms\Components\Textarea::make('home_subline_ar')->label('Text under the headline (Arabic)')->rows(2)->extraInputAttributes(['dir' => 'rtl']),
                    Forms\Components\Placeholder::make('home_caption_help')->hiddenLabel()->columnSpanFull()
                        ->content('The small rounded line at the very top of the banner is the "Caption" in Store & tax.'),
                    Forms\Components\Section::make('Box 1')->columns(3)->columnSpanFull()->compact()->collapsible()->schema([
                        Forms\Components\Select::make('home_box1_icon')->label('Icon')->options(collect(\App\Support\HomeContent::ICONS)->map(fn ($i) => $i[0])->all())->placeholder('Gold rate chart (default)'),
                        $text('home_box1_title', 'Title')->placeholder('Live gold rate'), $text('home_box1_title_ar', 'Title (Arabic)')->extraInputAttributes(['dir' => 'rtl']),
                        $text('home_box1_text', 'Text')->placeholder('Prices follow today\'s Dubai rate')->columnStart(2), $text('home_box1_text_ar', 'Text (Arabic)')->extraInputAttributes(['dir' => 'rtl']),
                    ]),
                    Forms\Components\Section::make('Box 2')->columns(3)->columnSpanFull()->compact()->collapsible()->schema([
                        Forms\Components\Select::make('home_box2_icon')->label('Icon')->options(collect(\App\Support\HomeContent::ICONS)->map(fn ($i) => $i[0])->all())->placeholder('Check badge (default)'),
                        $text('home_box2_title', 'Title')->placeholder('Hallmarked gold'), $text('home_box2_title_ar', 'Title (Arabic)')->extraInputAttributes(['dir' => 'rtl']),
                        $text('home_box2_text', 'Text')->placeholder('Certified purity on every piece')->columnStart(2), $text('home_box2_text_ar', 'Text (Arabic)')->extraInputAttributes(['dir' => 'rtl']),
                    ]),
                    Forms\Components\Section::make('Box 3')->columns(3)->columnSpanFull()->compact()->collapsible()->schema([
                        Forms\Components\Select::make('home_box3_icon')->label('Icon')->options(collect(\App\Support\HomeContent::ICONS)->map(fn ($i) => $i[0])->all())->placeholder('Shop / showroom (default)'),
                        $text('home_box3_title', 'Title')->placeholder('Collect in store'), $text('home_box3_title_ar', 'Title (Arabic)')->extraInputAttributes(['dir' => 'rtl']),
                        $text('home_box3_text', 'Text')->placeholder('Order online, pick up at our showroom')->columnStart(2), $text('home_box3_text_ar', 'Text (Arabic)')->extraInputAttributes(['dir' => 'rtl']),
                    ]),
                    Forms\Components\Section::make('Box 4')->columns(3)->columnSpanFull()->compact()->collapsible()->schema([
                        Forms\Components\Select::make('home_box4_icon')->label('Icon')->options(collect(\App\Support\HomeContent::ICONS)->map(fn ($i) => $i[0])->all())->placeholder('Lock (secure payment) (default)'),
                        $text('home_box4_title', 'Title')->placeholder('Secure payment'), $text('home_box4_title_ar', 'Title (Arabic)')->extraInputAttributes(['dir' => 'rtl']),
                        $text('home_box4_text', 'Text')->placeholder('Card, Apple Pay & Google Pay')->columnStart(2), $text('home_box4_text_ar', 'Text (Arabic)')->extraInputAttributes(['dir' => 'rtl']),
                    ]),
                ]),
                Forms\Components\Tabs\Tab::make('Branding')->icon('heroicon-o-swatch')->columns(2)->schema([
                    Forms\Components\FileUpload::make('brand_logo')->label('Logo')->image()->disk('public')->directory('branding')->maxSize(6144)
                        ->imageResizeMode('contain')->imageResizeTargetWidth('800')->imageResizeTargetHeight('240')
                        ->helperText('Shown at the top of every page instead of the shop name, and in the admin. PNG / SVG / WebP with a transparent background works best; wide logos look best.'),
                    Forms\Components\FileUpload::make('brand_logo_dark')->label('Logo for dark backgrounds (optional)')->image()->disk('public')->directory('branding')->maxSize(6144)
                        ->imageResizeMode('contain')->imageResizeTargetWidth('800')->imageResizeTargetHeight('240')
                        ->helperText('A light / gold version for the dark footer and dark mode. Empty = the normal logo.'),
                    Forms\Components\FileUpload::make('brand_favicon')->label('Favicon (browser tab icon)')->image()->disk('public')->directory('branding')->maxSize(2048)
                        ->imageResizeMode('cover')->imageCropAspectRatio('1:1')->imageResizeTargetWidth('512')->imageResizeTargetHeight('512')
                        ->helperText('A square picture (e.g. your emblem). Also used as the phone home-screen icon.'),
                    Forms\Components\FileUpload::make('home_hero_image')->label('Home page photo (the big circle)')->image()->disk('public')->directory('branding')->maxSize(6144)
                        ->imageResizeMode('cover')->imageResizeTargetWidth('1200')->imageResizeTargetHeight('1200')
                        ->helperText('Empty = the main photo of your newest featured product (or the drawn gold design).'),
                    Forms\Components\FileUpload::make('showroom_image')->label('Showroom photo')->image()->disk('public')->directory('branding')->maxSize(6144)
                        ->imageResizeMode('cover')->imageResizeTargetWidth('1600')->imageResizeTargetHeight('1000')
                        ->helperText('Shown on the home page in a “Visit our showroom” section with your address and directions. Empty = the section is hidden.'),
                    $text('showroom_title', 'Showroom heading')->placeholder('Visit our showroom'),
                    $text('showroom_title_ar', 'Showroom heading (Arabic)')->placeholder('زوروا معرضنا')->extraInputAttributes(['dir' => 'rtl']),
                ]),
                Forms\Components\Tabs\Tab::make('Admin display')->icon('heroicon-o-computer-desktop')->columns(2)->schema([
                    Forms\Components\Select::make('admin_text_size')->label('Admin text size')->native(false)->selectablePlaceholder(false)
                        ->options(['auto' => 'Automatic (bigger on large monitors)', 'normal' => 'Normal', 'large' => 'Large', 'xlarge' => 'Extra large'])
                        ->helperText('Changes the whole admin panel for every staff member. Each person can also use Ctrl + / Ctrl − in the browser (Ctrl 0 resets).'),
                ]),
                Forms\Components\Tabs\Tab::make('Pricing')->icon('heroicon-o-calculator')->columns(2)->schema([
                    Forms\Components\TextInput::make('default_vat_percent')->label('Default VAT %')->numeric()->required()->suffix('%'),
                    Forms\Components\Select::make('rounding_mode')->options(collect(RoundingMode::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()]))->required(),
                    Forms\Components\TextInput::make('rate_stale_hours')->label('Warn when gold rate is older than')->numeric()->suffix('hours')->required(),
                    Forms\Components\Toggle::make('block_checkout_when_stale')->label('Block checkout while the rate is stale')->inline(false),
                    $text('gold_rate_source_url', 'Gold rate website (Fetch button)')->url()->helperText('Page the "Fetch rate" button reads, e.g. https://dubaicityofgold.com/'),
                    Forms\Components\Toggle::make('gold_rate_auto_website')->label('Update rates from this website automatically')->inline(false)
                        ->helperText('Every 15 minutes (needs the cron job). Jumps over 15% are skipped for you to check. The "Updated" time on the shop shows the last check.'),
                    Forms\Components\Placeholder::make('auto_status')->label('Automatic update status')->columnSpanFull()
                        ->content(fn () => \App\Support\AutoTasks::cronText()."\n".\App\Support\AutoTasks::goldRateText())
                        ->extraAttributes(['class' => 'whitespace-pre-line']),
                    $text('gold_rate_api_url', 'Gold rate API URL (optional)')->url()->helperText('Only for a paid gold-rate data service (JSON) — not a normal website. Leave empty when you use the website switch above; that switch is used first.'),
                    $secret('gold_rate_api_key', 'Gold rate API key'),
                ]),
                Forms\Components\Tabs\Tab::make('Payments')->icon('heroicon-o-credit-card')->columns(3)->schema([
                    Forms\Components\Section::make('Gold price hold — time to pay')->icon('heroicon-o-clock')->columns(2)->columnSpanFull()
                        ->description('How long an online order keeps its gold price. If the customer has not paid by then, the order is cancelled automatically, the stock is released and no invoice is made. They must order again at the current gold price. (Cash on delivery is not affected.)')
                        ->schema([
                            Forms\Components\Select::make('payment_window_minutes')->label('Customer must pay within')->required()->native(false)->selectablePlaceholder(false)
                                ->options([0 => 'No time limit (off) — the order waits; items stay in the cart', 10 => '10 minutes', 15 => '15 minutes', 20 => '20 minutes', 30 => '30 minutes (recommended)', 45 => '45 minutes', 60 => '1 hour', 90 => '1 hour 30 minutes', 120 => '2 hours', 180 => '3 hours', 240 => '4 hours'])
                                ->helperText('Shorter = less risk when the gold rate moves. Stripe keeps its card page open for at least 30 minutes. "No time limit": unpaid orders are never cancelled automatically and keep the price from the moment of ordering — cancel old ones yourself in Orders → Unpaid. In every case the items stay in the customer\'s cart until the payment goes through.'),
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
                            $text('stripe_publishable_key', 'Publishable key')->placeholder('pk_test_… or pk_live_…')
                                ->helperText('Starts with pk_. With it, customers type their card on your own website (Stripe\'s secure card boxes) and can save it for next time. Without it, they are sent to Stripe\'s page.'),
                            Forms\Components\Toggle::make('stripe_embedded')->label('Card form on my website')->inline(false)
                                ->helperText('On: card boxes and saved cards on your payment page. Off: customers pay on Stripe\'s page.'),
                            $secret('stripe_webhook_secret', 'Webhook signing secret (optional)')->placeholder('whsec_…')
                                ->helperText(fn () => 'Recommended once the site is online: in Stripe → Developers → Webhooks add '.route('payments.webhook', 'stripe').' with the events checkout.session.completed and payment_intent.succeeded, then paste its signing secret here.'),
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
                        ->helperText('Customers enter their ID type, number and expiry as the last step before payment. They can save it for next time.'),
                    Forms\Components\Toggle::make('tax_free_enabled')->label('Offer tourist tax-free invoices')->inline(false)->live()
                        ->helperText('Checkout asks "Do you need a tax-free invoice?". Tourists who say yes must give a passport; you issue the tax-free tag (e.g. Planet) when the piece is handed over.'),
                    Forms\Components\TextInput::make('tax_free_min')->label('Minimum purchase for tax-free')->numeric()->minValue(0)->prefix('AED')
                        ->visible(fn (Forms\Get $get) => (bool) $get('tax_free_enabled'))->helperText('UAE rule: AED 250 including VAT.'),
                    Forms\Components\Fieldset::make('Estimated delivery / ready date (shown to customers)')->columns(3)->schema([
                        Forms\Components\Toggle::make('delivery_estimate')->label('Show the estimate')->inline(false)->columnSpanFull()
                            ->helperText('On the product page, bag, checkout and the customer\'s order page, e.g. "Estimated delivery: tomorrow – Thu 30 Oct" and "Ready to collect: today".'),
                        Forms\Components\TextInput::make('delivery_days_min')->label('Delivery: from (working days)')->numeric()->minValue(0)->maxValue(30),
                        Forms\Components\TextInput::make('delivery_days_max')->label('Delivery: up to (working days)')->numeric()->minValue(0)->maxValue(60),
                        Forms\Components\TextInput::make('pickup_ready_days')->label('Ready to collect after (working days)')->numeric()->minValue(0)->maxValue(30)->helperText('0 = same day'),
                        Forms\Components\TextInput::make('order_cutoff')->label('Orders after this time count from the next day')->placeholder('17:00')->regex('/^([01]\d|2[0-3]):[0-5]\d$/'),
                        Forms\Components\CheckboxList::make('working_days')->label('Working days')->columns(4)->columnSpan(2)
                            ->options([1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday']),
                    ]),
                    Forms\Components\Toggle::make('ip_lookup')->label('Find the city an order was placed from')->inline(false)
                        ->helperText('Shown on the order as "Ordered from: Sharjah, United Arab Emirates" (by the customer\'s internet address). Uses Cloudflare\'s location, or the free ipapi.co look-up. Tip: in Cloudflare → Rules → Transform Rules → Managed Transforms switch on "Add visitor location headers" for the city without any look-up.'),
                    Forms\Components\Toggle::make('checkout_auto_account')->label('Guests: "Keep me signed in" at checkout')->inline(false)
                        ->helperText('Ticked by default for guests. A free account is made with the order\'s email, the customer stays signed in on that phone and gets an email to choose a password.'),
                    Forms\Components\Select::make('delivery_location')->label('Customer\'s map location (delivery)')
                        ->options(['optional' => 'Ask — optional', 'required' => 'Required for delivery orders', 'off' => 'Not asked'])
                        ->helperText('At checkout the customer taps "Use my current location" or pastes a Google Maps link. The order shows "📍 Open in Google Maps" for the driver.'),
                    Forms\Components\Select::make('id_photos')->label('Photos of the ID (front and back)')->required()
                        ->options(['optional' => 'Optional — customer may upload', 'required' => 'Required — front and back must be uploaded', 'hidden' => 'Not asked'])
                        ->helperText('Photos are stored privately; only admins can open them from the order.'),
                    Forms\Components\Toggle::make('id_auto_read')->label('Read ID details from the photo')->inline(false)
                        ->helperText('When a customer adds a photo of the Emirates ID or passport, the ID number, expiry date and nationality are filled in for them (read on their own phone; they can correct it). Works best with the back of the Emirates ID or the passport photo page.'),
                    Forms\Components\Toggle::make('handover_otp')->label('Handover code on delivery / collection')->inline(false)
                        ->helperText('A 6-digit code is emailed and WhatsApped to the customer when the order is shipped (or ready for pickup). Staff must enter it to mark the order delivered.'),
                    Forms\Components\Toggle::make('product_store_map')->label('Show store map on every product page')->inline(false)
                        ->helperText('Google Maps location of each pickup branch. Set the pin under Settings → Branches & pickup.'),
                ]),
                Forms\Components\Tabs\Tab::make('Login & verification')->icon('heroicon-o-key')->columns(2)->schema([
                    Forms\Components\Toggle::make('phone_verification')->label('Customers must verify their mobile before ordering')->inline(false)->columnSpanFull()
                        ->helperText('A 6-digit code at the final checkout step (and at sign-up). The customer chooses WhatsApp, SMS (when set up in Settings → WhatsApp) or email. Until WhatsApp is set up, the code is shown on screen for testing.'),
                    Forms\Components\Toggle::make('phone_verify_email')->label('Allow the code by email too')->inline(false)
                        ->helperText('For customers without WhatsApp or mobile data. Note: an email code confirms the customer, not that the mobile number is theirs.'),
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
                    Forms\Components\Toggle::make('activity_log_enabled')->label('Record visitor activity')->inline(false)
                        ->helperText('Customers → Live visitors and Activity log: every page and step of customers, guests and staff, with IP and device.'),
                    Forms\Components\TextInput::make('activity_log_days')->label('Keep activity log for')->numeric()->minValue(0)->maxValue(3650)->suffix('days')
                        ->helperText('Older entries are deleted every night. 0 = keep for ever (the table grows quickly on a busy shop).'),
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
                Forms\Components\Tabs\Tab::make('Affiliates')->icon('heroicon-o-user-plus')->columns(2)->schema([
                    Forms\Components\Toggle::make('product_affiliate_commission')->label('Use the commission set on a product')->inline(false)
                        ->helperText('On: a product\'s own affiliate commission (Products → edit, or "Set affiliate commission" on several products) replaces every affiliate\'s rate on that piece. Off: every affiliate always earns their own rate.'),
                    Forms\Components\Toggle::make('affiliate_customer_contacts')->label('Affiliates see their customers\' phone and email')->inline(false)
                        ->helperText('In the affiliate portal → "My customers": customers who signed up with their link or ordered through them. Off = phone and email are partly hidden (e.g. +97150 *** 567).'),

                    Forms\Components\Toggle::make('affiliate_banner')->label('Show "You are shopping with …" bar')->inline(false)
                        ->helperText('Shown on phones that opened an affiliate\'s own price page (white page) or a product they shared from it; prices there include that affiliate\'s mark-up. To clear it on a phone, open the shop with ?aff=none at the end of the address. Admin and staff accounts are never linked.'),
                    Forms\Components\TextInput::make('affiliate_cookie_days')->label('Customer stays linked to the affiliate for')->numeric()->minValue(1)->maxValue(365)->suffix('days')
                        ->helperText('After clicking an affiliate link, orders within this time earn that affiliate commission. The last link clicked wins.'),
                    Forms\Components\Placeholder::make('affiliate_help')->hiddenLabel()->content('Defaults for new affiliates — each affiliate can have their own rates under Affiliates.'),
                    Forms\Components\TextInput::make('affiliate_default_making_percent')->label('Default: by-gram items, % of making charge')->numeric()->minValue(0)->maxValue(100)->suffix('%'),
                    Forms\Components\TextInput::make('affiliate_default_commission_per_gram')->label('Default: by-weight items, AED per gram (when chosen)')->numeric()->minValue(0)->prefix('AED'),
                    Forms\Components\TextInput::make('affiliate_default_commission_per_piece')->label('Default: AED per piece (when chosen)')->numeric()->minValue(0)->prefix('AED'),
                    Forms\Components\TextInput::make('affiliate_default_fixed_percent')->label('Default: fixed-price items, % of item total')->numeric()->minValue(0)->maxValue(100)->suffix('%'),
                    Forms\Components\TextInput::make('affiliate_default_max_making_markup')->label('Default max mark-up: by-weight items, % of making')->numeric()->minValue(0)->maxValue(500)->suffix('%'),
                    Forms\Components\TextInput::make('affiliate_default_max_markup')->label('Default max mark-up: fixed-price items, % of price')->numeric()->minValue(0)->maxValue(100)->suffix('%'),
                    Forms\Components\TextInput::make('affiliate_default_max_markup_amount')->label('Default max mark-up: amount per piece')->numeric()->minValue(0)->prefix('AED'),
                    Forms\Components\Toggle::make('affiliate_signup_enabled')->label('"Become an affiliate" page')->inline(false)->live()
                        ->helperText('On: a link in the shop footer opens an application form; you approve each request under Affiliates → Applications. Off: only you create affiliates.'),
                    Forms\Components\Toggle::make('affiliate_team_enabled')->label('"Build your team" (affiliates invite affiliates)')->inline(false)->live()
                        ->helperText('On: affiliates get a join link and earn the team leader share below on their recruits\' sales. Needs the "Become an affiliate" page.'),
                    Forms\Components\Toggle::make('affiliate_notifications')->label('WhatsApp / email affiliates about new sales and money ready to collect')->inline(false),
                    Forms\Components\TextInput::make('affiliate_recruiter_percent')->visible(fn (Forms\Get $get) => $get('affiliate_team_enabled'))->label('Team leader share of their recruits\' commission')->numeric()->minValue(0)->maxValue(50)->suffix('%')
                        ->helperText('Paid by the shop on top — the recruit\'s own commission is not reduced. 0 = off.'),
                    Forms\Components\TextInput::make('affiliate_price_lock_hours')->label('Price-lock links last')->numeric()->minValue(1)->maxValue(168)->suffix('hours'),
                    Forms\Components\FileUpload::make('affiliate_rate_backgrounds')->label('Backgrounds for the gold-rate picture')->columnSpanFull()
                        ->image()->multiple()->reorderable()->maxFiles(8)->disk('public')->directory('rate-backgrounds')->maxSize(6144)
                        ->imageResizeMode('cover')->imageResizeTargetWidth('1080')->imageResizeTargetHeight('1350')
                        ->helperText('Optional. Your own photos (shop front, jewellery, gold bars…) for affiliates\' gold-rate picture. The rates are written on top with a dark shade. Built-in designs are always offered too.'),
                    Forms\Components\Repeater::make('affiliate_tiers')->label('Levels by monthly sales (excl. VAT)')->columnSpanFull()->columns(3)->reorderable(false)
                        ->helperText('Each month an affiliate reaches a level, their commission gets the extra %. Example: Gold from AED 50,000 → +20% (10% making becomes 12%).')
                        ->schema([
                            Forms\Components\TextInput::make('name')->required()->maxLength(30)->placeholder('Gold'),
                            Forms\Components\TextInput::make('min_sales')->label('From monthly sales')->numeric()->minValue(0)->prefix('AED')->required(),
                            Forms\Components\TextInput::make('extra_percent')->label('Extra commission')->numeric()->minValue(0)->maxValue(200)->suffix('%')->required(),
                        ])->addActionLabel('Add level'),
                ]),
                Forms\Components\Tabs\Tab::make('Staff alerts')->icon('heroicon-o-bell-alert')->columns(2)->schema([
                    Forms\Components\Toggle::make('staff_order_alerts')->label('Tell staff about every new order')->inline(false)->columnSpanFull()
                        ->helperText('Sent when a cash-on-delivery order is placed and when an online order is paid: order number, customer, pieces, total and a link to the order.'),
                    Forms\Components\Textarea::make('staff_order_whatsapp')->label('Staff WhatsApp numbers')->rows(3)->placeholder("971501234567\n971559876543")
                        ->helperText('One per line, international format.'),
                    Forms\Components\Textarea::make('staff_order_emails')->label('Staff emails')->rows(3)->placeholder("sales@example.com\nmanager@example.com")
                        ->helperText('One per line.'),
                ]),
                Forms\Components\Tabs\Tab::make('Video calls')->icon('heroicon-o-video-camera')->columns(2)->schema([
                    Forms\Components\Toggle::make('video_call_enabled')->label('Live video call button on products')->inline(false)
                        ->helperText('Can also be switched off per product (Products → edit → Stock & visibility).'),
                    $text('video_call_hours', 'Hours shown to customers')->placeholder('Daily 10:00 – 22:00'),
                    $text('video_call_notify_email', 'Email staff about new calls')->email()->placeholder('Store email is used when empty'),
                    Forms\Components\Textarea::make('video_call_staff_whatsapp')->label('WhatsApp staff about new calls')->rows(2)->placeholder("971501234567\n971559876543")
                        ->helperText('One number per line (international format). Each gets the customer\'s name, phone and chosen app (Botim, Google Meet, Zoom, Teams).'),
                    $text('video_call_base_url', 'Video service address')->url()
                        ->helperText('In the UAE, Jitsi (meet.jit.si) is often blocked by internet providers — use a Google Meet, Zoom or Microsoft Teams meeting link instead. Every customer then joins that meeting and staff let them in. Staff can also send a fresh link per call (Live video calls → Send meeting link).'),
                    Forms\Components\Toggle::make('store_visit_button')->label('"Book a store visit to see this piece" button on products')->inline(false)
                        ->helperText('Switch off to hide the store-visit booking from product pages.'),
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
                    Forms\Components\Section::make('SMS — for customers without mobile data')->columns(2)->collapsible()
                        ->description('Optional. When set up, the mobile verification lets customers choose "WhatsApp" or "SMS" for their code.')
                        ->schema([
                            Forms\Components\Select::make('sms_provider')->label('Send SMS through')->live()->selectablePlaceholder(false)
                                ->options(['none' => 'Off (WhatsApp only)', 'twilio' => 'Twilio (uses the Account SID / token above)', 'http' => 'Another SMS company (web link)']),
                            $text('sms_from', 'Sender (number or name)')->placeholder('+12025550123 or ALMASROOR')->visible(fn (Forms\Get $get) => $get('sms_provider') === 'twilio'),
                            $text('sms_http_url', 'SMS web link')->columnSpanFull()->visible(fn (Forms\Get $get) => $get('sms_provider') === 'http')
                                ->placeholder('https://api.smscompany.ae/send?user=ME&pass=SECRET&to={to}&text={message}')
                                ->helperText('From your SMS company. Put {to} where the number goes (971501234567) or {to_plus} (+971…), and {message} for the text.'),
                            Forms\Components\Select::make('sms_http_method')->label('Send as')->options(['GET' => 'GET (link)', 'POST' => 'POST'])->default('GET')
                                ->visible(fn (Forms\Get $get) => $get('sms_provider') === 'http'),
                            Forms\Components\Section::make('Twilio account')->columns(2)->visible(fn (Forms\Get $get) => $get('sms_provider') === 'twilio' && $get('whatsapp_provider') !== 'twilio')->schema([
                                $text('twilio_sid', 'Account SID'), $secret('twilio_token', 'Auth token'),
                            ]),
                        ]),
                    Forms\Components\Placeholder::make('wa_hint')->label('')->columnSpanFull()
                        ->content('Sent automatically: order updates, the paid tax invoice, handover codes, abandoned-cart reminders and gold-drop alerts. Use "Send test WhatsApp" above to check.'),
                ]),
                Forms\Components\Tabs\Tab::make('Photos')->icon('heroicon-o-photo')->columns(2)->schema([
                    Forms\Components\Placeholder::make('photo_touch_help')->hiddenLabel()->columnSpanFull()
                        ->content('Ticked here = ticked by default on the product form ("Photo touch-up"). Staff can still untick it per save. Only photos added in that save are changed.'),
                    Forms\Components\Toggle::make('photo_touch_light')->label('Improve light & sharpness')->inline(false),
                    Forms\Components\Toggle::make('photo_touch_square')->label('Square photos with even background')->inline(false),
                    Forms\Components\Placeholder::make('photo_studio_help')->label('White background (free)')->columnSpanFull()
                        ->content('On any product: "Photo studio — white background" at the top of the edit page. The piece is cut out by a free AI model in your browser and placed on white with a soft shadow; the original photo is kept and can be put back.'),
                ]),
                Forms\Components\Tabs\Tab::make('Email')->icon('heroicon-o-envelope')->columns(2)->schema([
                    Forms\Components\Select::make('mail_mailer')->label('Send email')->live()
                        ->options(['resend' => 'Resend (email API — resend.com)', 'smtp' => 'Through my email provider (SMTP)', 'log' => 'Not yet — keep emails in the log (testing)'])
                        ->helperText('Works with Google Workspace / Gmail, Microsoft 365 / Outlook, Zoho, your web host\'s email, Brevo, Mailgun and others.'),
                    $text('mail_from_address', 'Send from (email address)')->email()->placeholder('orders@yourshop.ae'),
                    $text('mail_from_name', 'Sender name')->placeholder('Your Jewellery Shop'),
                    Forms\Components\Section::make('Resend API')->columns(1)->visible(fn (Forms\Get $get) => $get('mail_mailer') === 'resend')
                        ->description('In resend.com: Domains → add and verify your shop\'s domain, then API Keys → create a key with "Sending access". The "Send from" address above must use that verified domain (e.g. orders@yourshop.ae).')
                        ->schema([
                            $secret('resend_api_key', 'Resend API key')->placeholder('re_…')->helperText('Starts with re_. Stored only here, never in the code.'),
                        ]),
                    Forms\Components\Section::make('SMTP server')->columns(3)->visible(fn (Forms\Get $get) => $get('mail_mailer') === 'smtp')
                        ->description('Gmail / Google Workspace: smtp.gmail.com · 587 · TLS, password = an App password. Microsoft 365: smtp.office365.com · 587 · TLS. Zoho: smtp.zoho.com · 465 · SSL.')
                        ->schema([
                            $text('mail_host', 'SMTP host')->placeholder('smtp.gmail.com'),
                            Forms\Components\TextInput::make('mail_port')->label('Port')->numeric()->placeholder('587'),
                            Forms\Components\Select::make('mail_encryption')->label('Security')->options(['tls' => 'TLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None']),
                            $text('mail_username', 'Username')->placeholder('orders@yourshop.ae'),
                            $secret('mail_password', 'Password / app password'),
                        ]),
                    Forms\Components\TextInput::make('email_daily_limit')->label('Daily email limit')->numeric()->minValue(0)->suffix('emails a day')
                        ->helperText(fn () => 'Most hosting plans allow about 100 emails a day. Above this number, ordinary emails (offers, reminders, alerts) wait and go out the next day. Order emails with the invoice and verification codes are always sent at once. 0 = no limit. Sent today: '.\App\Messaging\EmailQueue::sentToday().' · waiting: '.\App\Messaging\EmailQueue::waiting().'.'),
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
            \Filament\Actions\ActionGroup::make([
                \Filament\Actions\Action::make('speedUp')->label('Speed up now (cache)')->icon('heroicon-o-bolt')
                    ->requiresConfirmation()->modalHeading('Speed up the shop')
                    ->modalDescription('Saves ready-made copies of settings, routes, pages and the admin panel (same as "php artisan optimize"). Safe to press any time. After an update or a .env change the copies are rebuilt automatically.')
                    ->action(function () {
                        // Built after this page's answer is sent: building it in the middle of the answer
                        // resets the admin panel's live parts (the window would stay grey).
                        \App\Support\SpeedCache::buildAfterResponse();
                        Notification::make()->success()->title('Speed cache is turning on')->body('Ready in a few seconds — reload the page to see "Speed cache: on". Pages then load faster.')->send();
                    }),
                \Filament\Actions\Action::make('storageLink')->label('Repair photo link')->icon('heroicon-o-photo')->color('gray')
                    ->requiresConfirmation()->modalHeading('Repair the photo link')
                    ->modalDescription('Creates public/storage, the link product and review photos are shown through (same as "php artisan storage:link"). Needed once on a new server.')
                    ->action(function () {
                        $ok = \App\Support\SpeedCache::storageLink();
                        $ok ? Notification::make()->success()->title('Photo link is working')->send()
                            : Notification::make()->danger()->title('The server does not allow links')->persistent()
                                ->body('Ask your host to enable symlinks, or in cPanel → Terminal run: php artisan storage:link')->send();
                    }),
                \Filament\Actions\Action::make('speedClear')->label('Clear speed cache')->icon('heroicon-o-arrow-path')->color('gray')
                    ->requiresConfirmation()->modalHeading('Clear the speed cache')
                    ->modalDescription('Removes the ready-made copies (same as "php artisan optimize:clear"). Use it if a change does not show. The shop keeps working, just a little slower until you press "Speed up now" again.')
                    ->action(function () {
                        \App\Support\SpeedCache::clear();
                        Notification::make()->success()->title('Speed cache cleared')->send();
                    }),
            ])->label(fn () => \App\Support\SpeedCache::isOn() ? 'Speed cache: on' : 'Speed cache: off')->icon('heroicon-o-bolt')->button()
                ->color(fn () => \App\Support\SpeedCache::isOn() ? 'success' : 'warning')
                ->visible(fn () => (bool) auth()->user()?->is_admin),
            \Filament\Actions\Action::make('deleteData')->label('Delete data')->icon('heroicon-o-trash')->color('danger')
                ->visible(fn () => (bool) auth()->user()?->is_admin)
                ->modalHeading('Delete shop data')->modalWidth('2xl')->modalIcon('heroicon-o-exclamation-triangle')->modalIconColor('danger')
                ->modalDescription('For clearing test data before going live, or old records. This cannot be undone — take a database backup first (phpMyAdmin → Export). Products, gold rates, settings, branches and staff logins are never deleted.')
                ->modalSubmitActionLabel('Delete permanently')
                ->form(fn () => [
                    Forms\Components\CheckboxList::make('groups')->label('What to delete')->required()->bulkToggleable()
                        ->options(function () {
                            $counts = \App\Support\DataCleanup::counts();

                            return collect(\App\Support\DataCleanup::GROUPS)->map(fn ($g, $k) => $g[0].' ('.number_format($counts[$k] ?? 0).')')->all();
                        })
                        ->descriptions(collect(\App\Support\DataCleanup::GROUPS)->map(fn ($g) => $g[1])->all()),
                    Forms\Components\TextInput::make('confirm')->label('Type DELETE to confirm')->required()->in(['DELETE'])
                        ->validationMessages(['in' => 'Type DELETE in capital letters.']),
                    Forms\Components\TextInput::make('password')->label('Your admin password')->password()->required()->currentPassword(),
                ])
                ->action(function (array $data) {
                    $done = \App\Support\DataCleanup::run($data['groups'], auth()->user());
                    Notification::make()->success()->title('Data deleted')->persistent()
                        ->body(collect($done)->map(fn ($n, $label) => $label.': '.number_format($n))->implode("\n"))->send();
                }),
            \Filament\Actions\Action::make('testEmail')->label('Send test email')->icon('heroicon-o-envelope')->color('gray')
                ->form([Forms\Components\TextInput::make('to')->label('Send to')->email()->required()->default(fn () => auth()->user()?->email)])
                ->modalDescription('Save your settings first. The test uses the saved email settings.')
                ->action(function (array $data) {
                    try {
                        \App\Support\MailSettings::apply();
                        \Illuminate\Support\Facades\Mail::to($data['to'])->send(new \App\Mail\ShopMessage('Test email from '.Store::get('store_name'), ['Your shop can send email. 🎉']));
                        config('mail.default') === 'log'
                            ? Notification::make()->warning()->title('Email is still in test mode')->body('Choose Resend or SMTP in Settings → Email and save.')->send()
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
