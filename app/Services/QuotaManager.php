<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

class QuotaManager
{
    public function checkQuota(User $user, string $service, int $amount = 1): bool
    {
        // Service argument might be redundant if User implies service, but keeping for config lookup
        $this->refreshQuotaIfNeeded($user, $service);

        $plan = 'free';
        if ($user->subscribed($service)) {
            $subscription = $user->subscription($service);
            $priceId = $subscription->stripe_price;
            
            // Re-using logic from controller/routes (should be centralized in User/Service really)
            // But for QuotaManager, maybe we just trust 'pro' vs 'free' logic is simplified here?
            // The original code used: $plan = $user->subscribed($service) ? 'pro' : 'free';
            // But 'plus' limits are in config too.
            // Let's improve this simply:
            
            $plusPrices = config("plans.{$service}.plus.prices", []);
            $proPrices = config("plans.{$service}.pro.prices", []);

            if (in_array($priceId, $plusPrices)) {
                $plan = 'plus';
            } elseif (in_array($priceId, $proPrices)) {
                $plan = 'pro';
            } else {
                 // Fallback
                 $plan = 'plus';
            }
        }
        
        $limit = config("plans.{$service}.{$plan}.limit", 0);

        return ($user->daily_usage + $amount) <= $limit;
    }

    public function incrementUsage(User $user, string $service, int $amount = 1, ?string $usageType = null): void
    {
        $this->refreshQuotaIfNeeded($user, $service);
        $user->increment('daily_usage', $amount);
        
        // Track breakdown by usage type
        // Track breakdown by usage type
        if ($usageType === 'video_fetch') {
            $user->increment('usage_video_fetch', $amount);
        }
        // AI Summary is now bundled, no separate tracking needed.
    }

    protected function refreshQuotaIfNeeded(User $user, string $service): void
    {
        $lastReset = $user->last_quota_reset ? Carbon::parse($user->last_quota_reset) : null;
        
        // Need correct plan to get period
        $plan = 'free';
        if ($user->subscribed($service)) {
             $subscription = $user->subscription($service);
             $priceId = $subscription->stripe_price; 
             $plusPrices = config("plans.{$service}.plus.prices", []);
             $proPrices = config("plans.{$service}.pro.prices", []);
             if (in_array($priceId, $plusPrices)) $plan = 'plus';
             elseif (in_array($priceId, $proPrices)) $plan = 'pro';
             else $plan = 'plus';
        }

        $period = config("plans.{$service}.{$plan}.period", 'daily');
        
        $shouldReset = false;

        if (!$lastReset) {
            $shouldReset = true;
        } elseif ($period === 'monthly') {
            $subscription = $user->subscription($service);
            if ($subscription) {
                // anniversaryDay should be clamped to the number of days in the month
                $day = $subscription->created_at->day;
                $lastAnniversary = now()->day($day > now()->daysInMonth ? now()->daysInMonth : $day)->startOfDay();
                
                if ($lastAnniversary->isFuture()) {
                    // Go back to the previous month's anniversary
                    $lastAnniversary = now()->subMonth();
                    $day = $subscription->created_at->day;
                    $lastAnniversary->day($day > $lastAnniversary->daysInMonth ? $lastAnniversary->daysInMonth : $day)->startOfDay();
                }
                
                $shouldReset = $lastReset->lessThan($lastAnniversary);
            } else {
                $shouldReset = !$lastReset->isSameMonth(Carbon::today());
            }
        } else {
            $shouldReset = !$lastReset->isSameDay(Carbon::today());
        }

        if ($shouldReset) {
            $user->update([
                'daily_usage' => 0,
                'last_quota_reset' => Carbon::now(),
            ]);
        }
    }

    public function getRemainingQuota(User $user, string $service): int
    {
        $this->refreshQuotaIfNeeded($user, $service);

        $plan = 'free';
        if ($user->subscribed($service)) {
            $subscription = $user->subscription($service);
            $priceId = $subscription->stripe_price;
            $plusPrices = config("plans.{$service}.plus.prices", []);
            $proPrices = config("plans.{$service}.pro.prices", []);

            if (in_array($priceId, $plusPrices)) {
                $plan = 'plus';
            } elseif (in_array($priceId, $proPrices)) {
                $plan = 'pro';
            } else {
                $plan = 'plus';
            }
        }
        
        $limit = config("plans.{$service}.{$plan}.limit", 0);
        $remaining = $limit - $user->daily_usage;

        return max(0, $remaining);
    }

    public function decrementUsage(User $user, string $service, int $amount = 1): void
    {
        // Refund usage (e.g. failed job or over-estimation)
        $user->decrement('daily_usage', $amount);
        
        // Safety check to ensure we don't go below 0?
        if ($user->daily_usage < 0) {
            $user->update(['daily_usage' => 0]);
        }
    }

    // Guest Tracking

    public function getGuestRemainingQuota(string $ip, string $userAgent, string $service): int
    {
        $limit = config("plans.{$service}.free.limit", 1);
        $usage = $this->getGuestUsage($ip, $userAgent, $service);
        return max(0, $limit - $usage);
    }

    public function decrementGuestUsage(string $ip, string $userAgent, string $service, int $amount = 1): void
    {
        $key = $this->getGuestKey($ip, $userAgent, $service);
        $current = $this->getGuestUsage($ip, $userAgent, $service);
        $newUsage = max(0, $current - $amount);
        
        \Illuminate\Support\Facades\Cache::put($key, $newUsage, Carbon::tomorrow());
    }

    public function checkGuestQuota(string $ip, string $userAgent, string $service, int $amount = 1): bool
    {
        $key = $this->getGuestKey($ip, $userAgent, $service);
        $usage = \Illuminate\Support\Facades\Cache::get($key, 0);
        $limit = config("plans.{$service}.free.limit", 1); 

        return ($usage + $amount) <= $limit;
    }

    public function incrementGuestUsage(string $ip, string $userAgent, string $service, int $amount = 1): void
    {
        $key = $this->getGuestKey($ip, $userAgent, $service);
        
        // Increment usage or set to amount if not exists. Expires at midnight.
        $usage = \Illuminate\Support\Facades\Cache::increment($key, $amount);
        
        if ($usage === $amount) {
            // First usage, ensure it expires at midnight
            \Illuminate\Support\Facades\Cache::put($key, $usage, Carbon::tomorrow());
        }
    }

    public function getGuestUsage(string $ip, string $userAgent, string $service): int
    {
        $key = $this->getGuestKey($ip, $userAgent, $service);
        return \Illuminate\Support\Facades\Cache::get($key, 0);
    }

    protected function getGuestKey(string $ip, string $userAgent, string $service): string
    {
        // Simple fingerprint: md5(ip + ua)
        $hash = md5($ip . $userAgent);
        return "guest_quota:{$service}:{$hash}";
    }
}
