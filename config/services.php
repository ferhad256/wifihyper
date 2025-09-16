<?php

return [

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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | JPesa Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for JPesa mobile money gateway
    |
    */
    'jpesa' => [
        'enabled' => env('JPESA_ENABLED', true), // Enabled by default
        'api_key' => env('JPESA_API_KEY'),
        'base_url' => env('JPESA_BASE_URL', 'https://my.jpesa.com/api/'),
        'callback_url' => env('JPESA_CALLBACK_URL', 'https://wifihyper.com/payment/jpesa/callback'),
        'timeout' => env('JPESA_TIMEOUT', 400),
        
        // Development settings
        'test_mode' => env('JPESA_TEST_MODE', false),
        'test_phone_prefix' => env('JPESA_TEST_PHONE_PREFIX', '256700'),
    ],

    /*
    |--------------------------------------------------------------------------
    | UG SMS Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for UG SMS gateway
    |
    */
    'ug_sms' => [
        'username' => env('UG_SMS_USERNAME'),
        'password' => env('UG_SMS_PASSWORD'),
        'sender_id' => env('UG_SMS_SENDER_ID', 'WIFIHYPER'),
        'base_url' => env('UG_SMS_BASE_URL', 'https://api.ug-sms.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Resend Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Resend email service
    |
    */
    'resend' => [
        'api_key' => env('RESEND_KEY'),
        'from_address' => env('MAIL_FROM_ADDRESS', 'noreply@wifihyper.com'),
        'from_name' => env('MAIL_FROM_NAME', 'WIFIHYPER'),
    ],

];
