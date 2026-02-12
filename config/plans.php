<?php

return [
    'youtube' => [
        'free' => [
            'limit' => 100,
            'period' => 'daily',
            'prices' => null,
            'features' => [
                '100 Credits / daily',
                'Basic Video Analysis',
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
                'Email Support',
            ],
        ],
        'pro' => [
            'limit' => 25000,
            'period' => 'monthly',
            'price_monthly' => 20,
            'price_yearly' => 200,
            'prices' => [
                'monthly' => env('STRIPE_YOUTUBE_PRO_MONTHLY_PRICE_ID', 'price_fake_yt_pro_mo'),
                'yearly' => env('STRIPE_YOUTUBE_PRO_YEARLY_PRICE_ID', 'price_fake_yt_pro_yr'),
            ],
            'features' => [
                'Everything in Plus',
                '25,000 Credits / monthly',
                'Advanced AI Sentiment Analysis',
                'Priority Processing',
                'API Access',
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
            'price_monthly' => 10,
            'price_yearly' => 100,
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
        ],
        'pro' => [
            'limit' => 20000,
            'period' => 'monthly',
            'price_monthly' => 30,
            'price_yearly' => 300,
            'prices' => [
                'monthly' => env('STRIPE_ZILLOW_PRO_MONTHLY_PRICE_ID', 'price_fake_zil_pro_mo'),
                'yearly' => env('STRIPE_ZILLOW_PRO_YEARLY_PRICE_ID', 'price_fake_zil_pro_yr'),
            ],
            'features' => [
                'Everything in Plus',
                '20,000 Credits / monthly',
                'Investment ROI Analysis',
                'Neighborhood Trends (AI)',
                'Priority Support',
            ],
        ]
    ]
];
