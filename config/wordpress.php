<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WordPress Multisite Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Chipkie WordPress Multisite installation.
    | Each subsite corresponds to a regional locale (AU, US, UK).
    | Authentication uses WordPress Application Passwords (WP 5.6+).
    |
    */

    'base_url' => env('WP_BASE_URL', 'https://chipkie.com'),

    'username' => env('WP_USERNAME', ''),

    // Generate via: WP Admin → Users → Profile → Application Passwords
    'application_password' => env('WP_APPLICATION_PASSWORD', ''),

    /*
    |--------------------------------------------------------------------------
    | Regional Locales
    |--------------------------------------------------------------------------
    |
    | Each key maps to a subsite path under the multisite domain.
    | e.g. chipkie.com/au, chipkie.com/us, chipkie.com/uk
    |
    */

    'locales' => [
        'au' => [
            'site_path'          => '/au',
            'label'              => 'Australia',
            'locale'             => 'en_AU',
            'currency'           => 'AUD',
            'currency_symbol'    => '$',
            'currency_prefix'    => 'A$',
            'tax_label'          => 'GST',
            'tax_rate'           => 10,
            'date_format'        => 'DD/MM/YYYY',
            'phone_country_code' => '+61',
            'spelling'           => 'british', // -ise, colour, etc.
        ],
        'us' => [
            'site_path'          => '/us',
            'label'              => 'United States',
            'locale'             => 'en_US',
            'currency'           => 'USD',
            'currency_symbol'    => '$',
            'currency_prefix'    => 'US$',
            'tax_label'          => 'Sales Tax',
            'tax_rate'           => null, // varies by state
            'date_format'        => 'MM/DD/YYYY',
            'phone_country_code' => '+1',
            'spelling'           => 'american', // -ize, color, etc.
        ],
        'uk' => [
            'site_path'          => '/uk',
            'label'              => 'United Kingdom',
            'locale'             => 'en_GB',
            'currency'           => 'GBP',
            'currency_symbol'    => '£',
            'currency_prefix'    => '£',
            'tax_label'          => 'VAT',
            'tax_rate'           => 20,
            'date_format'        => 'DD/MM/YYYY',
            'phone_country_code' => '+44',
            'spelling'           => 'british', // -ise, colour, etc.
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | REST API Settings
    |--------------------------------------------------------------------------
    */

    'rest_api_path'    => '/wp-json/wp/v2',
    'request_timeout'  => 30,
    'posts_per_page'   => 50,

    /*
    |--------------------------------------------------------------------------
    | Claude AI Configuration
    |--------------------------------------------------------------------------
    |
    | Used to power the content localization rewriter.
    |
    */

    'anthropic_api_key' => env('ANTHROPIC_API_KEY', ''),
    'anthropic_model'   => env('ANTHROPIC_MODEL', 'claude-opus-4-6'),

    /*
    |--------------------------------------------------------------------------
    | Localization Meta Key
    |--------------------------------------------------------------------------
    |
    | Post meta key used to track which AU source post a localized post
    | was generated from. Prevents duplicate localizations on re-runs.
    |
    */

    'source_meta_key' => '_chipkie_localized_from',

];
