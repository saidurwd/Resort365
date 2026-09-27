<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Central domain
    |--------------------------------------------------------------------------
    |
    | The platform's own domain (marketing, sign-up, platform console). Each
    | tenant is served from a subdomain of it: {slug}.{central_domain}.
    |
    */

    'central_domain' => env('TENANCY_CENTRAL_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'),

    /*
    |--------------------------------------------------------------------------
    | Reserved slugs
    |--------------------------------------------------------------------------
    |
    | Subdomains that can never be used by a tenant.
    |
    */

    'reserved_slugs' => [
        'admin', 'api', 'app', 'assets', 'auth', 'billing', 'blog', 'cdn', 'console', 'dashboard', 'dev',
        'docs', 'ftp', 'help', 'mail', 'platform', 'resort365', 'smtp', 'staging', 'static', 'status',
        'support', 'test', 'www',
    ],

];
