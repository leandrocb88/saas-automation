<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Reset Quota on Subscription Change (Upgrade/Downgrade)
        \Illuminate\Support\Facades\Event::listen(
            [
                \Laravel\Cashier\Events\SubscriptionCreated::class,
                \Laravel\Cashier\Events\SubscriptionUpdated::class,
            ],
            function ($event) {
                // $event->subscription is the Subscription model
                // We need the user
                $user = $event->subscription->user; 
                
                if ($user) {
                    $user->update([
                        'daily_usage' => 0,
                        'last_quota_reset' => now(),
                    ]);
                }
            }
        );
    }
}
