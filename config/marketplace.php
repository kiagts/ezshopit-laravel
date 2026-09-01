<?php

return [
    'name' => env('MARKETPLACE_NAME', env('APP_NAME', 'Marketplace')),

    'currency' => [
        'code' => env('MARKETPLACE_CURRENCY', 'USD'),
        'minor_unit' => (int) env('MARKETPLACE_CURRENCY_MINOR_UNIT', 2),
    ],

    'product_media' => [
        'disk' => env('MARKETPLACE_PRODUCT_MEDIA_DISK', 'public'),
        'max_gallery_images' => (int) env('MARKETPLACE_PRODUCT_MEDIA_MAX_GALLERY_IMAGES', 8),
        'max_variant_images' => (int) env('MARKETPLACE_PRODUCT_MEDIA_MAX_VARIANT_IMAGES', 3),
        'max_file_size_kb' => (int) env('MARKETPLACE_PRODUCT_MEDIA_MAX_FILE_SIZE_KB', 5120),
    ],

    'inventory' => [
        'default_low_stock_threshold' => (int) env('MARKETPLACE_DEFAULT_LOW_STOCK_THRESHOLD', 5),
    ],

    'cart' => [
        'max_quantity_per_item' => (int) env('MARKETPLACE_CART_MAX_QUANTITY_PER_ITEM', 20),
    ],

    'checkout' => [
        'max_customer_addresses' => (int) env('MARKETPLACE_MAX_CUSTOMER_ADDRESSES', 10),
        'supported_countries' => [
            'PK' => 'Pakistan',
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'NZ' => 'New Zealand',
            'AE' => 'United Arab Emirates',
        ],
    ],
];
