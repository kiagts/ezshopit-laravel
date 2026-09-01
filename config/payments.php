<?php

return [
    'online_payment_expiry_minutes' => (int) env('ONLINE_PAYMENT_EXPIRY_MINUTES', 30),

    'stripe' => [
        'enabled' => env('STRIPE_ENABLED', false),
        'public_key' => env('STRIPE_KEY'),
        'secret_key' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'supported_currencies' => [
            'usd',
            'pkr',
            'gbp',
            'eur',
            'cad',
            'aud',
            'aed',
            'sar',
        ],
    ],

    'methods' => [
        'cash_on_delivery' => [
            'enabled' => true,
            'label' => 'Cash on Delivery',
            'description' => 'Pay when your order is delivered.',
            'provider' => 'cash_on_delivery',
        ],
        'card' => [
            'enabled' => env('STRIPE_ENABLED', false),
            'label' => 'Card',
            'description' => 'Pay securely by debit or credit card.',
            'provider' => 'stripe',
        ],
    ],
];
