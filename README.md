# Jewellery e-commerce (UAE) — Laravel 12 + Filament 3

Online shop for a UAE gold and diamond jeweller. Prices come live from the daily gold rate, 5% VAT is always shown separately, and the site works in English and Arabic (RTL).

> **Status:** feature-complete build of the spec, running on test payments. The business details (name, TRN, address, WhatsApp number) are placeholders until you send the real ones (see [What I still need from you](#what-i-still-need-from-you)).

**Stack:** PHP 8.3 · Laravel 12 · MySQL 8 (InnoDB, utf8mb4) · Blade + Tailwind CSS 4 + Alpine.js · Filament 3 admin · Redis (cache + queue in production) · dompdf for tax invoices.

The spec asked for Laravel 11. I used Laravel 12, the current release: it is a drop-in successor and Laravel 11 no longer gets security fixes.

---

## Run it locally

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite          # or set DB_CONNECTION=mysql and the DB_* values
php artisan migrate --seed              # karats, VAT, UAE/GCC zones (+ demo catalogue in local)
php artisan storage:link
npm run build
php artisan serve                       # http://localhost:8000
php artisan queue:work                  # in a second terminal (or QUEUE_CONNECTION=sync)
php artisan test                        # 26 tests, incl. every testable acceptance criterion
```

- Admin: `http://localhost:8000/admin`. The demo login is `admin@example.com` / `ChangeMe-2026`. **Change it before going live.**
- With demo data, 24K is set to AED 218.18/g, which derives 22K at exactly AED 200.00/g. That reproduces the spec example: 5 g × 22K + 10% making + 5% VAT = **AED 1,155.00**.
- Card payments use a simulated hosted page while `card_gateway = mock` (local only, or production with `MOCK_PAYMENTS=true`).

---

## What is built, against the spec

| Spec | Where |
|---|---|
| **§2 Pricing engine.** Integer-fils maths, % or per-piece making charge, stones, wastage, VAT, rounding to 0.01 / 0.25 / 1 AED. | `app/Pricing/PriceCalculator.php`, `PricingService.php` |
| Prices are recomputed on every read and cached for 60 s. Any rate change bumps the cache version, so new prices show immediately. | `PricingService::forProduct()` |
| A rate change queues a recalculation in batches of 500, with a progress bar and a per-product price log. | `GoldRateService::updateRate()`, `Jobs/RecalculatePriceBatch.php`, `Filament/Widgets/RecalcProgress.php` |
| "Price valid as of … · Gold rate AED X/g" on every product page | `resources/views/shop/product.blade.php` |
| A rate older than 24 h shows an admin banner, and can optionally block checkout. | `filament/stale-rate-banner.blade.php`, Settings → Pricing |
| **§3 Schema.** All tables in the spec plus orders, coupons, campaigns, gift cards, loyalty, referrals, carts, reviews, redirects and the notification log. | `database/migrations/2026_10_17_*` |
| **§4.1 Gold rate manager.** Set the 24K rate and lower karats derive automatically (`base × karat/24`). Rates can be entered per g, per 10 g or per tola. History is append-only. | Admin → Gold rates |
| **§4.2 Products.** Live price preview as you type, a breakdown card, CSV import/export (Arabic-safe), a "last recalculated" time, low-stock badge and widget, WebP conversion of uploads. | Admin → Products |
| **§4.3 Orders.** Pending → Paid → Packed → Shipped → Delivered pipeline (plus cancel/refund), tracking number, FTA tax invoice PDF, manual price override with a logged reason. | Admin → Orders, `Shop/InvoiceService.php` |
| **§4.4 Customers and reviews.** Order history, loyalty balance and adjustments, photo reviews with a moderation queue. | Admin → Customers |
| **§4.5 Reports.** Sales by product, category and karat; gold rate vs. sales chart with correlation; abandoned carts; conversion; AOV; VAT collected. | Admin → Reports, Dashboard |
| **§5.1 Coupons.** Percentage, fixed AED, free shipping, BOGO and tiered types. Scopes: cart, category, products, karat, first order. Max uses (total and per customer), minimum cart, schedule, stacking, auto-apply. | `Promotions/CouponEngine.php`, `Shop/QuoteBuilder.php` |
| **§5.2 Campaigns.** Banner, countdown, auto-hide when the campaign ends. | Admin → Campaigns, homepage |
| **§5.3 Discount rules.** Strikethrough price and "Save X" badge. Discounts come off before VAT. A **hard floor** stops any line going below its gold value. | `PriceCalculator`, `QuoteBuilder::allocate()` |
| **§5.4** Gift cards (redeemed online at checkout, and in store from the admin), loyalty (1 pt per AED 10, redeemed at checkout), referral (AED 50 each way) | `Promotions/*`, Admin → Gift cards |
| **§6.1 SEO.** Auto meta title/description, Schema.org Product + Offer JSON-LD, `sitemap.xml` (with hreflang), `robots.txt`, canonical tags, OG/Twitter tags, a 301 redirect manager. | `Support/Seo.php`, `FeedController` |
| **§6.2 Pixels**, switched on in Admin → Settings: GA4, GTM, Meta, TikTok, Snapchat. Server-side purchase events go to GA4 Measurement Protocol and Meta CAPI. | `partials/pixels.blade.php`, `Jobs/SendServerSideConversion.php` |
| **§6.3 Feeds.** `/feed/google.xml` and `/feed/meta.xml` are cached for 1 h and **flushed on every rate change**. | `FeedController` |
| **§6.4 Email and WhatsApp.** Double opt-in newsletter (syncs to Mailchimp/Klaviyo), abandoned-cart messages at 1 h / 24 h / 72 h, "gold price dropped" alerts, order-status messages. WhatsApp goes through Twilio. | `Console/Commands/SendAbandonedCartReminders.php`, `Jobs/*`, `Messaging/*` |
| **§6.5 WhatsApp.** Floating button, "Share" and "Enquire" buttons with the product details pre-filled. | product page, layout |
| **§7 Storefront.** Live rate ticker, filters (karat, weight, price, category, stone), wishlist, compare (up to 4), the price is re-checked at checkout with a "price updated" notice, guest and registered checkout, address book. | `resources/views/shop/*` |
| **§7.1 Payments.** Telr and Stripe for cards, Tabby and Tamara (shown only within their limits), COD (admin toggle). Payment is confirmed server-to-server, and the webhook handlers are idempotent. | `app/Payments/*` |
| **§7.2** Full EN/AR with RTL mirroring (logical CSS properties), Cairo/Tajawal fonts, Arabic fields for products and categories | `lang/{en,ar}`, `Middleware/SetLocale.php` |
| **§7.3 Delivery.** Zones for Dubai, Abu Dhabi, Sharjah, Other Emirates and GCC, each with a flat rate and a free-above threshold. Store pickup per branch, with per-branch stock. | Admin → Settings → Delivery zones / Branches |
| **§8 Compliance.** VAT shown on every price, FTA invoice (supplier TRN, optional buyer TRN, gap-free `INV-YYYY-NNNNNN` numbering, per-line VAT, Arabic rendered correctly), UAE hallmark field. | `invoices/tax-invoice.blade.php` |
| **§9 Security.** HSTS and security headers, CSRF, rate limits on login and checkout, bcrypt, Eloquent bindings only (no raw user SQL), daily backups script | `Middleware/SecurityHeaders.php`, `scripts/backup-mysql.sh` |

### Acceptance criteria (§11)

`php artisan test` covers 1–12 in `tests/Feature/AcceptanceTest.php`:

- Criteria 1–7 and 10–12 are tested directly.
- Criterion 8 (Tabby/Tamara eligibility) is tested on the offer logic.
- Criterion 9 (abandoned cart) checks that the 1-hour WhatsApp message is sent, logged and not sent early.

Two of them need your live accounts before they can be signed off for real:

- **8:** Tabby/Tamara checkout against their sandboxes.
- **12:** a run through Google's Rich Results Test on the live URL. The JSON-LD includes every required field.

**A note on criterion 5 and the gold floor.** The spec says both "EID15 = 15% off the subtotal" and "never below gold cost". On a plain 10%-making ring, 15% off would cut into the gold value, so the floor wins and the discount is capped at the making charge. On pieces with stones or higher making charges, the full 15% applies. The test covers both cases.

---

## Going live

1. **Server:** Ubuntu VPS (Hetzner/DigitalOcean) with PHP 8.3-FPM, MySQL 8, Redis, nginx (`deploy/nginx.conf`), behind Cloudflare.
2. **`.env`:** `APP_ENV=production`, `APP_DEBUG=false`, `DB_CONNECTION=mysql`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis`, mail settings, and the payment keys.
3. **Deploy:** `composer install --no-dev -o && npm ci && npm run build && php artisan migrate --force && php artisan db:seed --class=ReferenceDataSeeder --force && php artisan optimize && php artisan storage:link`
4. **Queue worker:** `deploy/supervisor-queue.conf`. **Scheduler:** `* * * * * php /var/www/shop/artisan schedule:run`. It runs abandoned carts every 10 min, the rate API every 15 min, and the feed refresh hourly.
5. **Backups:** cron `scripts/backup-mysql.sh` (daily, 30-day retention, off-server via rclone).
6. **Webhooks:** register `https://<domain>/webhooks/stripe`, `/webhooks/tabby`, `/webhooks/tamara` with each provider.
7. **Admin:** create the real admin, delete the demo one, then fill in Admin → Settings (TRN, address, WhatsApp, pixels, Twilio).

---

## Known limits of this build

These are not finished yet, and I'm telling you up front:

- **Telr, Tabby and Tamara are written against their documented APIs but have not been run against their sandboxes** (no merchant accounts yet). Stripe webhook signature checks and the simulated card flow are tested. Plan a half-day of sandbox testing per provider once the accounts arrive.
- **PayTabs isn't included.** The spec said "Telr *or* PayTabs" and I built Telr. PayTabs is a small adapter if you prefer it.
- **Gift cards** are issued in the admin (or automatically as referral rewards) and can be redeemed online or in store. **Buying a gift card online isn't built yet.**
- **Admin 2FA (optional in the spec) isn't included.** Filament 3 needs a plugin for it, or we upgrade to Filament 4, which has it built in.
- **Web push:** the OneSignal SDK loads when configured, so visitors can subscribe. Campaign and price-drop pushes are sent from the OneSignal dashboard for now; server-side push sending isn't built.
- **WhatsApp** messages go through Twilio. Marketing messages outside the 24-hour window need **Meta-approved templates**, which you request through Twilio once the number is approved. Until Twilio is configured, every message is written to the notification log instead of being sent.
- **Product photos:** the demo uses drawn placeholders. Upload real photography in Admin → Products, or via the `images` column of the CSV import.

## What I still need from you

1. Business name (EN/AR), **TRN**, legal name, address, phone, WhatsApp number
2. Gold rate source: manual daily entry (works now) or an API subscription (set the URL in Settings → Pricing)
3. Payment accounts: Telr and/or Stripe keys, plus **Tabby and Tamara merchant accounts** (approval takes about 1 week, so apply now)
4. Product CSV (`Admin → Products → Export CSV` gives you the exact template) and photos
5. Logo, brand colours and fonts (the current theme is a neutral ivory/gold placeholder)
6. Delivery rates per emirate, and the free-delivery threshold (seeded with sensible defaults)
7. Domain and hosting access. I can set these up for you.
