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
    | Yo! Payments Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Yo! Payments mobile money gateway
    |
    */
    'yo_payments' => [
        'username' => env('YO_PAYMENTS_USERNAME'),
        'password' => env('YO_PAYMENTS_PASSWORD'),
        'base_url' => env('YO_PAYMENTS_BASE_URL', 'https://paymentsapi1.yo.co.ug/ybs/task.php'),
        'fallback_url' => env('YO_PAYMENTS_FALLBACK_URL', 'https://paymentsapi2.yo.co.ug/ybs/task.php'),
        'public_key_enabled' => env('YO_PAYMENTS_PUBLIC_KEY_ENABLED', false),
        'private_key_path' => env('YO_PAYMENTS_PRIVATE_KEY_PATH', 'storage/keys/yo_payments_private_key.pem'),
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
