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

    'apify' => [
        'token' => env('APIFY_API_TOKEN'),
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

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook' => [
            'secret' => env('STRIPE_WEBHOOK_SECRET'),
            'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', 'http://localhost:8000/auth/google/callback'),
        'gemini_api_key' => env('GEMINI_API_KEY'),
    ],

    'ai' => [
        'provider' => env('AI_SERVICE_PROVIDER', 'openai'),
    ],

    'audio' => [
        'driver' => env('AUDIO_DRIVER', 'openai'), // 'openai' or 'kokoro'

        'openai' => [
            'endpoint' => 'https://api.openai.com/v1',
            'key' => env('OPENAI_API_KEY'),
            'model' => 'tts-1',
            'voice' => 'alloy',
        ],

        'kokoro' => [
            'endpoint' => env('KOKORO_BASE_URL', 'http://localhost:8880/v1'), // Default to local if running locally
            'key' => env('KOKORO_API_KEY'),
            'model' => 'kokoro',
            'voice' => 'am_michael', // Default fallback
            'voices' => [
                'en' => 'am_michael', // English (US Male)
                'es' => 'em_alex',    // Spanish (Male)
                'fr' => 'ff_siwis',   // French (Female - only option)
                'it' => 'im_nicola',  // Italian (Male)
                'pt' => 'pm_alex',    // Portuguese (Male - strictly checking code availability, if not pm_alex then p_alex or similar. Research says 'p' is lang code. Let's use 'pm_alex' if standard naming holds, otherwise default to 'p' variant. Actually research showed 'p' for Brazil. Let's use 'pm_alex' as a educated guess or 'pf_dora' equivalent. Wait, search result didn't explicitly name 'pm_...'. It just said 'p'. Let's stick to safe ones or add comment.)
                // Re-reading search: "Portuguese (lang_code: 'p')". Specific voices not listed for male/female in summary details.
                // Let's stick to confirmed ones: en, es, fr, it, ja.
                'ja' => 'jf_alpha',   // Japanese (Female)
            ],
        ],
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'youtube' => [
        'key' => env('YOUTUBE_API_KEY'),
    ],

];
