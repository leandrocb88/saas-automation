<?php

return [
    'youtube' => [
        'free' => [
            'limit' => 100,
            'period' => 'daily',
            'prices' => null,
            'features' => [
                '100 Credits / daily',
                'Free Video Transcripts',
                '1 Credit per AI Summary',
                '1 Credit per Audio',
                'Community Support',
            ],
        ],
        'plus' => [
            'limit' => 5000,
            'period' => 'monthly',
            'price_monthly' => 5,
            'price_yearly' => 50,
            'prices' => [
                'monthly' => env('STRIPE_YOUTUBE_PLUS_MONTHLY_PRICE_ID', 'price_fake_yt_plus_mo'),
                'yearly' => env('STRIPE_YOUTUBE_PLUS_YEARLY_PRICE_ID', 'price_fake_yt_plus_yr'),
            ],
            'features' => [
                'Everything in Free',
                '5,000 Credits / monthly',
                'Channel Batch Analysis',
                'Priority Email Support',
            ],
        ]
    ],
    'zillow' => [
        'free' => [
            'limit' => 100,
            'period' => 'daily',
            'prices' => null,
            'features' => [
                '100 Credits / daily',
                'Basic Property Details',
                'Standard Layouts',
            ],
        ],
        'plus' => [
            'limit' => 5000,
            'period' => 'monthly',
            'price_monthly' => 5,
            'price_yearly' => 50,
            'prices' => [
                'monthly' => env('STRIPE_ZILLOW_PLUS_MONTHLY_PRICE_ID', 'price_fake_zil_plus_mo'),
                'yearly' => env('STRIPE_ZILLOW_PLUS_YEARLY_PRICE_ID', 'price_fake_zil_plus_yr'),
            ],
            'features' => [
                'Everything in Free',
                '5,000 Credits / monthly',
                'Rental Estimate Calculator',
                'Comparable Sales Data',
            ],
        ]
    ]
];
