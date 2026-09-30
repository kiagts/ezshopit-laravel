<?php

return [
    // Platform-issued commercial records; not a claim of statutory tax compliance.
    'issuer' => env('DOCUMENT_ISSUER', 'marketplace'),
    'legal_name' => env('DOCUMENT_LEGAL_NAME'),
    'tax_number' => env('DOCUMENT_TAX_NUMBER'),
    'business_address' => env('DOCUMENT_BUSINESS_ADDRESS'),
    'website' => env('APP_URL'),
    'fallback_locale' => env('APP_LOCALE', 'en'),
];
