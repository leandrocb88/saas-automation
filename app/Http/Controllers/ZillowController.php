<?php

namespace App\Http\Controllers;

use App\Services\QuotaManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\ApifyService;

class ZillowController extends Controller
{
    public function index()
    {
        return Inertia::render('Zillow/Home');
    }

    public function process(Request $request, QuotaManager $quotaManager, ApifyService $apify)
    {
        $request->validate([
            'address_or_url' => ['required', 'string', 'min:5'],
        ]);

        $user = auth()->user();
        $ip = $request->ip();
        $userAgent = $request->userAgent();

        $cost = config('credits.zillow.property_fetch', 1);
        
        // 1. Check Quota
        if ($user) {
            if (!$quotaManager->checkQuota($user, 'zillow', $cost)) {
                return back()->withErrors(['limit' => 'You have reached your daily limit. Upgrade to Pro for more!']);
            }
        } else {
             // Guest Check
             if (!$quotaManager->checkGuestQuota($ip, $userAgent, 'zillow', $cost)) {
                  return back()->withErrors(['limit' => 'Daily free limit reached. Please register or upgrade.']);
             }
        }

        // 2. Trigger Zillow Scraper (Mock for now, real later)
        // For strictly "Analysis", we probably want property details.
        
        // Mock Response for UI Development phase
        $propertyData = [
            'address' => $request->address_or_url,
            'price' => '$450,000',
            'estimate' => '$460,000',
            'rent_estimate' => '$2,500/mo',
            'details' => [
                'bedrooms' => 3,
                'bathrooms' => 2,
                'sqft' => 1800,
                'year_built' => 1995,
            ]
        ];

        // 3. Increment Usage
        if ($user) {
            $quotaManager->incrementUsage($user, 'zillow', $cost);
        } else {
            $quotaManager->incrementGuestUsage($ip, $userAgent, 'zillow', $cost);
        }

        return Inertia::render('Zillow/Summary', [
            'searchQuery' => $request->address_or_url,
            'property' => $propertyData,
            'usage' => $user ? [
                'used' => $user->daily_usage, 
                'limit' => config('plans.zillow.' . ($user->subscribed('zillow') ? 'pro' : 'free') . '.limit'),
                'is_guest' => false,
            ] : [
                'used' => $quotaManager->getGuestUsage($ip, $userAgent, 'zillow'),
                'limit' => config('plans.zillow.free.limit'),
                'is_guest' => true,
            ]
        ]);
    }
}
