<?php

return [

    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
    ],

    'travel' => [
        'provider' => env('TRAVEL_PROVIDER', 'fake'),
        'travel_api' => [
            'environment' => env('TRAVEL_API_ENVIRONMENT', 'cert'),
            // `epr_v2` derives credentials from V1:EPR:PCC:DOMAIN and does not
            // require a separate application Client ID or Client Secret.
            'auth_scheme' => env('TRAVEL_API_AUTH_SCHEME', 'oauth_client'),
            'access_token' => env('TRAVEL_API_ACCESS_TOKEN'),
            'cert_url' => env('TRAVEL_API_CERT_URL'),
            'production_url' => env('TRAVEL_API_PRODUCTION_URL'),
            'client_id' => env('TRAVEL_API_CLIENT_ID'),
            'client_secret' => env('TRAVEL_API_CLIENT_SECRET'),
            'user_id' => env('TRAVEL_API_USER_ID'),
            'password' => env('TRAVEL_API_PASSWORD'),
            'application_id' => env('TRAVEL_API_APPLICATION_ID'),
            'epr' => env('TRAVEL_API_EPR'),
            'pcc' => env('TRAVEL_API_PCC'),
            'organization' => env('TRAVEL_API_ORGANIZATION'),
            'domain' => env('TRAVEL_API_DOMAIN'),
            'timeout' => (int) env('TRAVEL_API_TIMEOUT', 30),
            'token_path' => env('TRAVEL_API_TOKEN_PATH', '/v2/auth/token'),
            'flight_shop_path' => env('TRAVEL_API_FLIGHT_SHOP_PATH', '/v5/offers/shop'),
            'flight_revalidate_path' => env('TRAVEL_API_FLIGHT_REVALIDATE_PATH', '/v5/shop/flights/revalidate'),
            'order_create_path' => env('TRAVEL_API_ORDER_CREATE_PATH', '/v1/trip/orders/create'),
            'order_cancel_path' => env('TRAVEL_API_ORDER_CANCEL_PATH', '/v1/trip/orders/cancel'),
            'order_change_path' => env('TRAVEL_API_ORDER_CHANGE_PATH', '/v1/trip/orders/change'),
            'booking_create_path' => env('TRAVEL_API_BOOKING_CREATE_PATH', '/v1/trip/orders/createBooking'),
            'agency_number'       => env('TRAVEL_API_AGENCY_NUMBER'),
            'agency_state'        => env('TRAVEL_API_AGENCY_STATE', 'Lagos'),
            'hotel_avail_path' => env('TRAVEL_API_HOTEL_AVAIL_PATH', '/v5/get/hotelavail'),
            'hotel_details_path' => env('TRAVEL_API_HOTEL_DETAILS_PATH', '/v5/get/hoteldetails'),
            'hotel_price_check_path' => env('TRAVEL_API_HOTEL_PRICE_CHECK_PATH', '/v5/get/hotel/pricecheck'),
            'requestor_id' => env('TRAVEL_API_REQUESTOR_ID', '1'),
            'company_code' => env('TRAVEL_API_COMPANY_CODE', 'TN'),
            'max_itineraries' => (int) env('TRAVEL_API_MAX_ITINERARIES', 50),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
