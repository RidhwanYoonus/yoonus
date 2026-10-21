<?php

return [
    'currency' => 'AED',

    // 1 tola = 11.6638 g (UAE/South-Asian trade unit).
    'rate_units' => [
        'gram' => 1,
        'ten_gram' => 10,
        'tola' => 11.6638,
    ],

    'recalc_chunk' => 500,
    'price_cache_seconds' => 60,

    'payment_methods' => ['cod', 'card', 'tabby', 'tamara'],

    // Fallbacks for every admin-editable setting (Admin → Settings overrides these).
    'defaults' => [
        'store_name' => env('SHOP_NAME', 'Your Jewellery Shop'),
        'store_name_ar' => env('SHOP_NAME_AR', 'متجر المجوهرات'),
        'store_legal_name' => env('SHOP_LEGAL_NAME', 'Your Jewellery Shop LLC'),
        'store_trn' => env('SHOP_TRN', '100000000000003'),
        'store_address' => env('SHOP_ADDRESS', 'Shop address, Dubai, United Arab Emirates'),
        'store_phone' => env('SHOP_PHONE', '+971 4 000 0000'),
        'store_email' => env('SHOP_EMAIL', 'hello@example.ae'),
        'whatsapp_number' => env('SHOP_WHATSAPP', '971500000000'),
        'whatsapp_button' => true,
        'store_caption' => 'Order direct from the factory · Collect from our premium retail store',
        'store_caption_ar' => 'اطلب مباشرة من المصنع · واستلم من متجرنا الفاخر',
        'making_badge' => true,
        'making_badge_highlight_below' => 5,   // % making charge at or below this gets the gold "Only X% making" badge

        'default_vat_percent' => 5.00,
        'rounding_mode' => 'fils',          // fils | quarter | dirham
        'rate_stale_hours' => 24,
        'block_checkout_when_stale' => false,

        // Payment / card fee charged to the customer at checkout: % of the amount paid + fixed AED. VAT is added on top.
        'card_fee_percent' => 2.5,
        'card_fee_fixed' => 0,
        'tabby_fee_percent' => 0,
        'tabby_fee_fixed' => 0,
        'tamara_fee_percent' => 0,
        'tamara_fee_fixed' => 0,
        'cod_fee_percent' => 0,
        'cod_fee_fixed' => 0,

        'applepay_enabled' => true,
        'googlepay_enabled' => true,
        'cod_enabled' => true,
        'cod_max_total' => 10000,
        'card_gateway' => 'mock',           // mock | mettpay | stripe | telr
        'mettpay_api_url' => 'https://application.mettpay.com/api/orders/createorder',
        'mettpay_merchant_key' => null,
        'mettpay_merchant_secret' => null,
        'mettpay_status_url' => null,
        'payment_window_minutes' => 30,
        'id_photos' => 'optional',
        'affiliate_cookie_days' => 30,
        'affiliate_default_making_percent' => 10,
        'affiliate_default_commission_per_gram' => 2,
        'affiliate_default_commission_per_piece' => 10,
        'affiliate_notifications' => true,
        'affiliate_signup_enabled' => false,   // public "Become an affiliate" page
        'affiliate_team_enabled' => false,     // "Build your team" + team leader share
        'affiliate_recruiter_percent' => 10,
        'affiliate_price_lock_hours' => 24,
        'affiliate_tiers' => [
            ['name' => 'Silver', 'min_sales' => 0, 'extra_percent' => 0],
            ['name' => 'Gold', 'min_sales' => 50000, 'extra_percent' => 20],
            ['name' => 'Platinum', 'min_sales' => 150000, 'extra_percent' => 50],
        ],
        'affiliate_default_fixed_percent' => 3,
        'affiliate_default_max_markup' => 10,          // fixed-price items, % of price
        'affiliate_default_max_making_markup' => 30,   // by-weight items, % of making charge
        'affiliate_default_max_markup_amount' => 100,  // AED per piece
        'admin_two_factor' => false,
        'require_terms_acceptance' => true,
        'policy_emirate' => 'Dubai',
        'policy_report_hours' => 48,
        'admin_idle_minutes' => 30,
        'shop_hide_sold' => false,
        'tax_free_enabled' => true,             // checkout asks tourists if they want a tax-free invoice
        'tax_free_min' => 250,
        'affiliate_rate_backgrounds' => [],      // photos behind the affiliates' gold-rate picture
        'seo_auto_fill' => true,                // empty SEO boxes are written from the product / category details              // shop lists start with "Available only" ticked
        'activity_log_enabled' => true,          // Customers → Activity log / Live visitors
        'activity_log_days' => 90,               // 0 = keep for ever
        'stripe_secret_key' => null,
        'stripe_webhook_secret' => null,
        'tabby_enabled' => true,
        'tabby_min' => 100,
        'tabby_max' => 15000,
        'tamara_enabled' => true,
        'tamara_min' => 100,
        'tamara_max' => 20000,

        'loyalty_earn_per_aed' => 10,       // 1 point per AED 10
        'loyalty_point_value' => 0.10,      // AED per point on redemption
        'loyalty_min_redeem' => 100,
        'referral_reward' => 50,

        'pickup_enabled' => true,
        'delivery_enabled' => true,
        'delivery_off_message' => 'Home delivery is paused at the moment — please collect your order from our store.',
        'delivery_off_message_ar' => 'التوصيل للمنازل متوقف حالياً — يرجى استلام طلبك من متجرنا.',
        'product_store_map' => true,
        'id_verification' => 'delivery',   // proof of ID at checkout: delivery | all | off
        'handover_otp' => true,
        'phone_verification' => true,       // mobile must be verified (WhatsApp code) before placing an order
        'google_client_id' => null,
        'google_client_secret' => null,            // customer gives staff a one-time code on delivery / collection

        'video_call_enabled' => true,
        'video_call_hours' => 'Daily 10:00 – 22:00',
        'video_call_base_url' => 'https://meet.jit.si',
        'video_call_notify_email' => null,

        'ga4_id' => null,
        'ga4_api_secret' => null,
        'gtm_id' => null,
        'meta_pixel_id' => null,
        'meta_capi_token' => null,
        'tiktok_pixel_id' => null,
        'snap_pixel_id' => null,
        'onesignal_app_id' => null,
        'onesignal_api_key' => null,

        'twilio_sid' => null,
        'twilio_token' => null,
        'twilio_whatsapp_from' => null,
        'translation_provider' => 'auto',      // auto | mymemory | google | deepl
        'google_translate_key' => null,
        'deepl_key' => null,
        'whatsapp_provider' => 'meta',          // meta (WhatsApp Cloud API) | twilio
        'meta_wa_phone_number_id' => null,
        'meta_wa_token' => null,
        'meta_wa_api_version' => 'v21.0',
        'meta_wa_template' => null,             // approved Utility template with one body variable {{1}}
        'meta_wa_invoice_template' => null,     // approved template with a Document header + body {{1}}
        'meta_wa_template_lang' => 'en',
        'whatsapp_send_invoice' => true,
        'mail_mailer' => null,                  // resend (API) or smtp to send real email; empty = use the .env setting
        'resend_api_key' => null,
        'mail_host' => null,
        'mail_port' => 587,
        'mail_username' => null,
        'mail_password' => null,
        'mail_encryption' => 'tls',
        'mail_from_address' => null,
        'mail_from_name' => null,
        'mailchimp_api_key' => null,
        'mailchimp_list_id' => null,
        'klaviyo_api_key' => null,
        'klaviyo_list_id' => null,

        'gold_rate_source_url' => 'https://dubaicityofgold.com/',   // "Fetch rate" button in Gold rate manager
        'gold_rate_api_url' => null,        // optional automatic rate feed
        'gold_rate_api_key' => null,
    ],
];
