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

        'cod_enabled' => true,
        'cod_max_total' => 10000,
        'card_gateway' => 'mock',           // mock | stripe | telr | paytabs
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
        'mailchimp_api_key' => null,
        'mailchimp_list_id' => null,
        'klaviyo_api_key' => null,
        'klaviyo_list_id' => null,

        'gold_rate_source_url' => 'https://dubaicityofgold.com/',   // "Fetch rate" button in Gold rate manager
        'gold_rate_api_url' => null,        // optional automatic rate feed
        'gold_rate_api_key' => null,
    ],
];
