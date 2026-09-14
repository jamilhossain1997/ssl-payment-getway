<?php

return [
    // Set SSLCOMMERZ_MODE=live in production .env
    'mode' => env('SSLCOMMERZ_MODE', 'sandbox'), // sandbox | live

    'store_id' => env('SSLCOMMERZ_STORE_ID'),
    'store_password' => env('SSLCOMMERZ_STORE_PASSWORD'),

    'urls' => [
        'sandbox' => [
            'init' => 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php',
            'validate' => 'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php',
            'refund' => 'https://sandbox.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php',
        ],
        'live' => [
            'init' => 'https://securepay.sslcommerz.com/gwprocess/v4/api.php',
            'validate' => 'https://securepay.sslcommerz.com/validator/api/validationserverAPI.php',
            'refund' => 'https://securepay.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php',
        ],
    ],

    // These MUST point to routes registered in routes/web.php (see PaymentController)
    'success_url' => env('APP_URL') . '/payment/success',
    'fail_url' => env('APP_URL') . '/payment/fail',
    'cancel_url' => env('APP_URL') . '/payment/cancel',
    'ipn_url' => env('APP_URL') . '/payment/ipn',

    'currency' => env('SSLCOMMERZ_CURRENCY', 'BDT'),
];
