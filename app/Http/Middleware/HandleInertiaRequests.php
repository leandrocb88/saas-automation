<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $userData = $user ? $user->toArray() : null;

        if ($user) {
            $host = $request->getHost();
            $service = str_contains($host, 'zillow') ? 'zillow' : 'youtube';
            $userData['is_subscribed'] = $user->subscribed($service);
            
            // Calculate Quota
            $plan = 'free';
            if ($user->subscribed($service)) { // Use original check method, don't rely on the prop
                $subscription = $user->subscription($service);
                $priceId = $subscription->stripe_price;
                $plusPrices = config("plans.{$service}.plus.prices", []);
                $proPrices = config("plans.{$service}.pro.prices", []);
                
                if (in_array($priceId, $plusPrices)) $plan = 'plus';
                elseif (in_array($priceId, $proPrices)) $plan = 'pro';
                else $plan = 'plus';
            }
            
            $limit = config("plans.{$service}.{$plan}.limit", 100);
            $period = config("plans.{$service}.{$plan}.period", 'daily');

            // Calculate Next Reset Date
            $next_reset_at = (function() use ($user, $service, $plan, $period) {
                if ($period === 'daily') {
                    return now()->addDay()->startOfDay()->format('F j, Y');
                }
                
                $subscription = $user->subscription($service);
                if (!$subscription) return now()->addDay()->startOfDay()->format('F j, Y');

                $start = $subscription->created_at;
                $day = $start->day;
                
                $next = now()->day(min($day, now()->daysInMonth))->startOfDay();
                
                if ($next->isPast() || $next->isToday()) {
                    $next = now()->addMonthNoOverflow();
                    $next->day(min($day, $next->daysInMonth))->startOfDay();
                }
                
                return $next->format('F j, Y');
            })();

            $userData['quota'] = [
                'used' => $user->daily_usage,
                'limit' => $limit,
                'remaining' => max(0, $limit - $user->daily_usage),
                'plan' => $plan,
                'plan_name' => ucfirst($plan),
                'period' => $period,
                'next_reset_at' => $next_reset_at,
            ];
        } else {
            // Guest Quota Tracking
            $host = $request->getHost();
            $service = str_contains($host, 'zillow') ? 'zillow' : 'youtube';
            
            /** @var \App\Services\QuotaManager $quotaManager */
            $quotaManager = app(\App\Services\QuotaManager::class);
            try {
                $used = $quotaManager->getGuestUsage($request->ip(), $request->userAgent(), $service);
            } catch (\Exception $e) {
                $used = 0;
            }
            $limit = config("plans.{$service}.free.limit", 1);
            
            $userData = null; // Ensure UserData is null
            
            // We'll pass guest info separately in 'guest' key for clarity, 
            // or just mix it into a 'quota' top level key?
            // Let's stick to auth.guest structure as planned.
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $userData,
                'guest' => !$user ? [
                    'quota' => [
                        'used' => $used ?? 0,
                        'limit' => $limit ?? 1,
                        'remaining' => max(0, ($limit ?? 1) - ($used ?? 0)),
                    ]
                ] : null,
            ],
            'settings' => (function() {
                try {
                    return [
                        'sign_up_enabled' => \App\Models\Setting::where('key', 'sign_up_enabled')->value('value') !== 'false',
                        'admin_only_access' => \App\Models\Setting::where('key', 'admin_only_access')->value('value') === 'true',
                    ];
                } catch (\Exception $e) {
                    return [
                        'sign_up_enabled' => true,
                        'admin_only_access' => false,
                    ];
                }
            })(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'ziggy' => function () use ($request) {
                return array_merge((new Ziggy)->toArray(), [
                    'location' => $request->url(),
                ]);
            },
        ];
    }
}
