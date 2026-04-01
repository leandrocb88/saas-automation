<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

class QuotaManager
{
    public function checkQuota(User $user, string $service, int $amount = 1): bool
    {
        if ($amount <= 0) return true;
        
        // Service argument might be redundant if User implies service, but keeping for config lookup
        $this->refreshQuotaIfNeeded($user, $service);

        $limit = $this->getLimit($user, $service);
        $totalAvailable = $limit + $user->purchased_credits;

        return ($user->daily_usage + $amount) <= $totalAvailable;
    }

    public function getTotalQuota(User $user, string $service): int
    {
        $this->refreshQuotaIfNeeded($user, $service);
        return $this->getLimit($user, $service) + $user->purchased_credits;
    }

    protected function getLimit(User $user, string $service): int
    {
        $plan = 'free';
        if ($user->subscribed($service)) {
            $subscription = $user->subscription($service);
            $priceId = $subscription->stripe_price;
            
            $plusPrices = config("plans.{$service}.plus.prices", []);

            if (in_array($priceId, $plusPrices)) {
                $plan = 'plus';
            } else {
                 $plan = 'pro'; // Default to pro if not plus for now, or just consistent fallback
            }
        }
        
        return config("plans.{$service}.{$plan}.limit", 0);
    }

    public function getCost(?User $user, string $service, string $action): int
    {
        if ($action === 'transcript') return 0;
        
        // Since we decided transcripts are free for everyone, we just return the config value
        // The config for 'transcript' is now 0.
        return config("credits.{$service}.{$action}", 1);
    }

    public function incrementUsage(User $user, string $service, int $amount = 1, ?string $usageType = null): void
    {
        $this->refreshQuotaIfNeeded($user, $service);
        
        // Determine the plan limit
        $plan = $user->subscribed($service) ? 'plus' : 'free';
        $limit = config("plans.{$service}.{$plan}.limit", 0);
        
        $currentUsage = $user->daily_usage;
        $newUsage = $currentUsage + $amount;
        
        // If the new usage exceeds the base plan limit, deduct from purchased_credits
        if ($newUsage > $limit) {
            $excess = $newUsage - max($currentUsage, $limit);
            if ($user->purchased_credits >= $excess) {
                $user->decrement('purchased_credits', $excess);
            } else {
                // If they don't have enough purchased credits, just zero it out (should ideally be prevented by checkQuota)
                $user->update(['purchased_credits' => 0]);
            }
        }

        $user->increment('daily_usage', $amount);
        
        // Track breakdown by usage type
        if ($usageType === 'video_fetch') {
            $user->increment('usage_video_fetch', $amount);
        }
    }

    protected function refreshQuotaIfNeeded(User $user, string $service): void
    {
        $lastReset = $user->last_quota_reset ? Carbon::parse($user->last_quota_reset) : null;
        
        // Need correct plan to get period
        $plan = 'free';
        if ($user->subscribed($service)) {
             $plan = 'plus';
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
            $plan = 'plus';
        }
        
        $limit = config("plans.{$service}.{$plan}.limit", 0);
        $remainingFromPlan = $limit - $user->daily_usage;
        $remainingFromPlan = max(0, $remainingFromPlan);

        return $remainingFromPlan + $user->purchased_credits;
    }

    public function decrementUsage(User $user, string $service, int $amount = 1): void
    {
        $plan = $user->subscribed($service) ? 'plus' : 'free';
        $limit = config("plans.{$service}.{$plan}.limit", 0);
        
        // If current usage is over the limit, it means purchased credits were used. Refunding means giving back purchased credits first.
        if ($user->daily_usage > $limit) {
            $overage = $user->daily_usage - $limit;
            $refundToPurchased = min($amount, $overage);
            
            if ($refundToPurchased > 0) {
                $user->increment('purchased_credits', $refundToPurchased);
            }
        }

        // Refund overall daily usage
        $user->decrement('daily_usage', $amount);
        
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
        if ($amount <= 0) return true;
        
        $key = $this->getGuestKey($ip, $userAgent, $service);
        $usage = \Illuminate\Support\Facades\Cache::get($key, 0);
        $limit = config("plans.{$service}.free.limit", 1); 

        return ($usage + $amount) <= $limit;
    }

    public function incrementGuestUsage(string $ip, string $userAgent, string $service, int $amount = 1): void
    {
        $key = $this->getGuestKey($ip, $userAgent, $service);
        $usage = \Illuminate\Support\Facades\Cache::get($key, 0);
        $newUsage = $usage + $amount;
        
        \Illuminate\Support\Facades\Cache::put($key, $newUsage, Carbon::tomorrow());
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
