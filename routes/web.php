<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Services\QuotaManager;

Route::middleware('web')->group(function () {
    $host = request()->getHost();

    if (str_contains($host, 'youtube') || app()->runningInConsole()) {
        Route::get('/', [App\Http\Controllers\YouTubeController::class, 'index'])->name('youtube.home');
        Route::get('/process', function () { return redirect()->route('youtube.home'); });
        Route::post('/process', [App\Http\Controllers\YouTubeController::class, 'process'])->name('youtube.process');
        
        Route::get('/history', [App\Http\Controllers\YouTubeController::class, 'history'])->name('youtube.history');
        Route::delete('/history', [App\Http\Controllers\YouTubeController::class, 'clearHistory'])->name('youtube.clear');
        Route::get('/history/{video}', [App\Http\Controllers\YouTubeController::class, 'show'])->name('youtube.show');
        Route::delete('/history/{video}', [App\Http\Controllers\YouTubeController::class, 'destroy'])->name('youtube.destroy');
        Route::post('/summary/{video}', [App\Http\Controllers\YouTubeController::class, 'generateSummary'])->name('youtube.summary.generate');

        Route::get('/channels', [App\Http\Controllers\YouTubeController::class, 'channel'])->name('youtube.channel');
        Route::post('/channels/process', [App\Http\Controllers\YouTubeController::class, 'processChannel'])->name('youtube.channel.process');



        Route::middleware(['auth', 'not.blocked'])->group(function() {
            Route::get('/subscriptions', [App\Http\Controllers\YouTubeController::class, 'subscriptions'])->name('youtube.subscriptions');
            Route::post('/subscriptions', [App\Http\Controllers\YouTubeController::class, 'storeSubscription'])->name('youtube.subscriptions.store');
            Route::delete('/subscriptions/{channel}', [App\Http\Controllers\YouTubeController::class, 'destroySubscription'])->name('youtube.subscriptions.destroy');
            Route::post('/schedule', [App\Http\Controllers\YouTubeController::class, 'updateSchedule'])->name('youtube.schedule.update');
            Route::get('/digest', [App\Http\Controllers\YouTubeController::class, 'digest'])->name('youtube.digest');
            Route::get('/digest/{token}', [App\Http\Controllers\YouTubeController::class, 'showDigestRun'])->name('youtube.digest.show');

        });
    }
    // Zillow Domain Logic
    elseif (str_contains($host, 'zillow')) {
        Route::get('/', [App\Http\Controllers\ZillowController::class, 'index'])->name('zillow.home');
        Route::post('/process', [App\Http\Controllers\ZillowController::class, 'process'])->name('zillow.process');
    }
    // Default / Dashboard Domain Logic
    else {
        Route::get('/', function () {
            return Inertia::render('Welcome', [
                'canLogin' => Route::has('login'),
                'canRegister' => Route::has('register'),
                'laravelVersion' => Application::VERSION,
                'phpVersion' => PHP_VERSION,
            ]);
        });
    }

    Route::get('/plans', function () {
        $host = request()->getHost();
        $service = str_contains($host, 'zillow') ? 'zillow' : 'youtube';
        $plans = config("plans.{$service}");
        
        $user = auth()->user();
        $currentPlan = 'free';
        $currentPeriod = null; // 'monthly' or 'yearly'
        $onGracePeriod = false;

        if ($user && $user->subscribed($service)) {
            $subscription = $user->subscription($service);
            $priceId = $subscription->stripe_price;
            
            if ($subscription->onGracePeriod()) {
                $onGracePeriod = true;
            }
            
            // Refined Check for Plan & Period
            $plusPrices = config("plans.{$service}.plus.prices", []);
            $proPrices = config("plans.{$service}.pro.prices", []);

            if (in_array($priceId, $plusPrices)) {
                $currentPlan = 'plus';
                $currentPeriod = ($priceId === $plusPrices['yearly']) ? 'yearly' : 'monthly';
            } elseif (in_array($priceId, $proPrices)) {
                $currentPlan = 'pro';
                $currentPeriod = ($priceId === $proPrices['yearly']) ? 'yearly' : 'monthly';
            } else {
                $currentPlan = 'plus'; // fallback
                $currentPeriod = 'monthly';
            }
        }

        return Inertia::render('Plans', [
            'service' => ucfirst($service),
            'plans' => $plans,
            'current_plan' => $currentPlan,
            'current_period' => $currentPeriod,
            'on_grace_period' => $onGracePeriod,
        ]);
    })->name('plans');

    Route::get('/dashboard', function () {
        return redirect()->route('youtube.subscriptions');
    })->middleware(['auth', 'verified'])->name('dashboard');

    Route::get('/billing', function (QuotaManager $quotaManager) {
        $user = auth()->user();
        $service = $user->service_type; // 'youtube' or 'zillow'

        // Ensure quota is fresh
        $quotaManager->checkQuota($user, $service);

        // Get Plan ID (pro/free)
        $plan = 'free';
        $subscription_ends_at = null;
        
        if ($user->subscribed($service)) {
            $subscription = $user->subscription($service);
            
            if ($subscription->onGracePeriod()) {
                $subscription_ends_at = $subscription->ends_at->format('F j, Y');
            }

            $priceId = $subscription->stripe_price;
            
            // Check against configured prices
            $plusPrices = config("plans.{$service}.plus.prices");
            $proPrices = config("plans.{$service}.pro.prices");

            if (in_array($priceId, $plusPrices)) {
                $plan = 'plus';
            } elseif (in_array($priceId, $proPrices)) {
                $plan = 'pro';
            } else {
                // Fallback or legacy handling
                $plan = 'plus'; 
            }
        }
        
        return Inertia::render('Billing', [
            'service' => ucfirst($service), // 'YouTube' or 'Zillow'
            'subscription_ends_at' => $subscription_ends_at,
            'usageStats' => [
                'used' => $user->daily_usage,
                'limit' => config("plans.{$service}.{$plan}.limit"),
                'period' => config("plans.{$service}.{$plan}.period"),
                'plan_name' => ucfirst($plan),
                'is_pro' => $user->subscribed($service),

                // We could rely on HandleInertiaRequests for next_reset_at since we added it there,
                // but passing it explicitly here guarantees it fits the UsageStats interface exactly.
                'next_reset_at' => (function() use ($user, $service, $plan) {
                    if ($plan === 'free') {
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
                })(),
            ]
        ]);
    })->middleware(['auth', 'verified'])->name('billing');

    // TEMP DEBUG ROUTE
    Route::get('/debug-subs', function () {
        return \App\Models\User::with('subscriptions')->get();
    });
});

Route::middleware(['auth', 'not.blocked'])->group(function () {
    Route::get('/checkout/{plan}', [App\Http\Controllers\CheckoutController::class, 'subscription'])->name('checkout.subscription');
    Route::post('/subscription/cancel', [App\Http\Controllers\CheckoutController::class, 'cancel'])->name('subscription.cancel');
    Route::post('/subscription/resume', [App\Http\Controllers\CheckoutController::class, 'resume'])->name('subscription.resume');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index'])->name('admin.dashboard');
    Route::post('/admin/users/{user}/block', [AdminController::class, 'toggleBlock'])->name('admin.users.block');
});
