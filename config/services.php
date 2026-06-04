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

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'image_tag_model' => env('OPENAI_IMAGE_TAG_MODEL', 'o4-mini'),
    ],

    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
        'sender_email' => env('BREVO_SENDER_EMAIL', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
        'sender_name' => env('BREVO_SENDER_NAME', env('MAIL_FROM_NAME', 'Stimergie Image Hub')),
        'templates' => [
            'registration' => env('BREVO_TEMPLATE_REGISTRATION'),
            'shared_album_invitation' => env('BREVO_TEMPLATE_SHARED_ALBUM_INVITATION'),
            'monthly_image_digest' => env('BREVO_TEMPLATE_MONTHLY_IMAGE_DIGEST'),
        ],
    ],

];
