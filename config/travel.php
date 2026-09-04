<?php

return [
    'default_currency' => env('TRAVEL_DEFAULT_CURRENCY', 'NGN'),
    'support' => [
        'email' => env('TRAVEL_SUPPORT_EMAIL', 'info@karossytravels.com'),
        'phone' => env('TRAVEL_SUPPORT_PHONE'),
        'whatsapp' => env('TRAVEL_WHATSAPP_PHONE', env('TRAVEL_SUPPORT_PHONE')),
    ],
    'features' => [
        'flights' => true,
        'hotels' => false,
        'holidays' => false,
        'charter' => false,
        'pilgrimage' => false,
        'visas' => false,
        'cars' => false,
    ],
    'offers' => [
        'ttl_minutes' => (int) env('TRAVEL_OFFER_TTL_MINUTES', 15),
    ],
    'checkout' => [
        // Local development only. Production can never use this shortcut.
        'demo_payment_enabled' => (bool) env('TRAVEL_DEMO_PAYMENT_ENABLED', false),
        // Allows the successful Paystack test-popup callback to finish a CERT
        // booking on localhost, where Paystack cannot deliver a webhook. The
        // controller additionally requires local env + pk_test + no secret key.
        'local_callback_finalization' => (bool) env('TRAVEL_LOCAL_CALLBACK_FINALIZATION', false),
    ],
    'pricing' => [
        // 100 basis points = 1%. Keep at zero until commercial rules are approved.
        'markup_basis_points' => [
            'consumer' => (int) env('TRAVEL_CONSUMER_MARKUP_BPS', 0),
            'business' => (int) env('TRAVEL_BUSINESS_MARKUP_BPS', 0),
            'employee' => (int) env('TRAVEL_EMPLOYEE_MARKUP_BPS', 0),
            'corporate' => (int) env('TRAVEL_CORPORATE_MARKUP_BPS', 0),
            'affiliate' => (int) env('TRAVEL_AFFILIATE_MARKUP_BPS', 0),
        ],
    ],
    'currency' => [
        'supported' => ['NGN', 'USD', 'GBP', 'EUR', 'CAD', 'ZAR', 'AED'],
        'rates_url' => env('EXCHANGE_RATES_URL', 'https://open.er-api.com/v6/latest/USD'),
        'cache_hours' => (int) env('EXCHANGE_RATES_CACHE_HOURS', 6),
        'timeout' => (int) env('EXCHANGE_RATES_TIMEOUT', 5),
        'fallback_usd_rates' => [
            'NGN' => (float) env('USD_NGN_FALLBACK_RATE', 1600),
        ],
        // Production enables this explicitly. Nigeria resolves to NGN; every other
        // country (and a failed lookup) resolves to the safe USD fallback.
        'geo_lookup_enabled' => (bool) env('GEOIP_LOOKUP_ENABLED', false),
        'geo_url' => env('GEOIP_URL', 'https://ipapi.co/{ip}/json/'),
        'local_country' => env('LOCAL_COUNTRY_CODE', 'NG'),
    ],
];
