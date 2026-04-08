<?php


use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Services\QuotaManager;

use App\Http\Controllers\Api\YoutubeWebhookController;

Route::post('/webhooks/youtube/dataset-sync', [YoutubeWebhookController::class, 'handle'])->name('webhooks.youtube.dataset-sync');

Route::middleware('web')->group(function () {
    $host = request()->getHost();

    // Zillow Domain Logic
    if (str_contains($host, 'zillow')) {
        Route::get('/', [App\Http\Controllers\ZillowController::class, 'index'])->name('zillow.home');
        Route::post('/process', [App\Http\Controllers\ZillowController::class, 'process'])->name('zillow.process');
    }
    // Default / YouTube Domain Logic (including Railway)
    else {
        Route::get('/', [App\Http\Controllers\YouTubeController::class, 'index'])->name('youtube.home');
        Route::get('/process', function () { return redirect()->route('youtube.home'); });
        Route::middleware(['guest.access'])->group(function() {
            Route::post('/process', [App\Http\Controllers\YouTubeController::class, 'process'])->name('youtube.process');
            Route::post('/summary/{video}', [App\Http\Controllers\YouTubeController::class, 'generateSummary'])->name('youtube.summary.generate');
            Route::post('/freeze-credits', [App\Http\Controllers\YouTubeController::class, 'freezeCredits'])->name('youtube.freeze_credits');
            Route::post('/videos/{video}/translate', [App\Http\Controllers\YouTubeController::class, 'translate'])->name('youtube.translate');
            Route::post('/videos/{video}/translate-summary', [App\Http\Controllers\YouTubeController::class, 'translateSummary'])->name('youtube.translate_summary');
            Route::get('/channels', [App\Http\Controllers\YouTubeController::class, 'channel'])->name('youtube.channel');
            Route::post('/channels/process', [App\Http\Controllers\YouTubeController::class, 'processChannel'])->name('youtube.channel.process');
            Route::get('/history', [App\Http\Controllers\YouTubeController::class, 'history'])->name('youtube.history');
            Route::delete('/history/clear', [App\Http\Controllers\YouTubeController::class, 'clearHistory'])->name('youtube.clear');
            Route::get('/video/{video}', [App\Http\Controllers\YouTubeController::class, 'show'])->name('youtube.show');
            Route::delete('/video/{video}', [App\Http\Controllers\YouTubeController::class, 'destroy'])->name('youtube.destroy');

            Route::get('/videos/{video}/status', [App\Http\Controllers\YouTubeController::class, 'videoStatus'])->name('video.status');
        });

        Route::middleware(['auth', 'not.blocked'])->group(function() {
            Route::get('/subscriptions', [App\Http\Controllers\YouTubeController::class, 'subscriptions'])->name('youtube.subscriptions');
            Route::post('/subscriptions', [App\Http\Controllers\YouTubeController::class, 'storeSubscription'])->name('youtube.subscriptions.store');
            Route::delete('/subscriptions/{channel}', [App\Http\Controllers\YouTubeController::class, 'destroySubscription'])->name('youtube.subscriptions.destroy');
            Route::post('/subscriptions/{channel}/toggle', [App\Http\Controllers\YouTubeController::class, 'toggleSubscriptionStatus'])->name('youtube.subscriptions.toggle');
            Route::resource('digests', \App\Http\Controllers\DigestController::class)->except(['show']);
            Route::get('/history/digests', [App\Http\Controllers\DigestRunController::class, 'index'])->name('digest_runs.index');
            Route::get('/digest-runs/{digestRun}/pdf', [App\Http\Controllers\DigestRunController::class, 'downloadPdf'])->name('digest_runs.pdf');
            Route::get('/digest-runs/{digestRun}/audio', [App\Http\Controllers\DigestRunController::class, 'downloadAudio'])->name('digest_runs.audio');
            Route::get('/digest-runs/{digestRun}/status', [App\Http\Controllers\DigestRunController::class, 'status'])->name('digest_runs.status');
            Route::get('/digest/{token}', [App\Http\Controllers\YouTubeController::class, 'digestShow'])->name('youtube.digest.show');

            // Video Downloads & Status
            Route::get('/videos/{video}/pdf', [App\Http\Controllers\YouTubeController::class, 'downloadVideoPdf'])->name('video.pdf');
            Route::get('/videos/{video}/audio', [App\Http\Controllers\YouTubeController::class, 'downloadVideoAudio'])->name('video.audio');

            // YouTube OAuth – Import Subscriptions
            Route::get('/auth/youtube/redirect', [App\Http\Controllers\YouTubeController::class, 'youtubeAuthRedirect'])->name('youtube.auth.redirect');
            Route::get('/auth/youtube/callback', [App\Http\Controllers\YouTubeController::class, 'youtubeAuthCallback'])->name('youtube.auth.callback');

            // Resource Datasets (Pro-only)
            Route::middleware(function ($request, $next) {
                if (!$request->user() || !$request->user()->subscribed('youtube')) {
                    return redirect()->route('plans')->withErrors(['error' => 'The Knowledge Base feature is exclusive to Plus members.']);
                }
                return $next($request);
            })->group(function() {
                Route::get('/datasets', [\App\Http\Controllers\DatasetController::class, 'index'])->name('datasets.index');
                Route::post('/datasets', [\App\Http\Controllers\DatasetController::class, 'store'])->name('datasets.store');
                Route::delete('/datasets/{dataset}', [\App\Http\Controllers\DatasetController::class, 'destroy'])->name('datasets.destroy');
                Route::post('/datasets/{dataset}/sync', [\App\Http\Controllers\DatasetController::class, 'sync'])->name('datasets.sync');
                Route::put('/datasets/{dataset}', [\App\Http\Controllers\DatasetController::class, 'update'])->name('datasets.update');
                Route::patch('/datasets/{dataset}/toggle', [\App\Http\Controllers\DatasetController::class, 'toggle'])->name('datasets.toggle');
                Route::get('/datasets/{dataset}/download', [\App\Http\Controllers\DatasetController::class, 'download'])->name('datasets.download');
                Route::get('/datasets/{dataset}/videos', [\App\Http\Controllers\DatasetController::class, 'videos'])->name('datasets.videos');
            });
        });

        // Redirect /welcome to /
        Route::get('/welcome', function () {
            return redirect()->route('youtube.home');
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

            if (in_array($priceId, $plusPrices)) {
                $currentPlan = 'plus';
                $currentPeriod = ($priceId === $plusPrices['yearly']) ? 'yearly' : 'monthly';
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

            if (in_array($priceId, $plusPrices)) {
                $plan = 'plus';
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
                'purchased_credits' => $user->purchased_credits,

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
    Route::get('/checkout/credits', [App\Http\Controllers\CheckoutController::class, 'buyCredits'])->name('checkout.credits');
    Route::get('/checkout/credits/success', [App\Http\Controllers\CheckoutController::class, 'creditSuccess'])->name('checkout.credits.success');
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
    Route::post('/admin/users', [AdminController::class, 'store'])->name('admin.users.store');
    Route::post('/admin/users/{user}/block', [AdminController::class, 'toggleBlock'])->name('admin.users.block');
    Route::post('/admin/users/{user}/reset-credits', [AdminController::class, 'resetCredits'])->name('admin.users.reset_credits');
    Route::put('/admin/users/{user}', [AdminController::class, 'update'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [AdminController::class, 'destroy'])->name('admin.users.destroy');
    Route::post('/admin/settings/guest-access', [AdminController::class, 'toggleGuestAccess'])->name('admin.settings.guest_access');
    Route::post('/admin/settings/sign-up', [AdminController::class, 'toggleRegistration'])->name('admin.settings.sign_up');
    Route::post('/admin/settings/admin-only', [AdminController::class, 'toggleAdminOnly'])->name('admin.settings.admin_only');

    // Digest Recovery & Debug Route
    Route::get('/admin/digests/debug', function() {
        // 1. Reset any digests that have been in "processing" for more than 10 minutes (likely crashed)
        $recovered = \App\Models\Digest::where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes(10))
            ->update(['status' => 'ready']);

        // 2. Clear SPECIFIC status for testing if requested
        if (request()->has('reset_all')) {
            \App\Models\Digest::query()->update(['status' => 'ready']);
        }

        // 3. Generate a detailed report
        $digests = \App\Models\Digest::with('user')->where('is_active', true)->get();
        
        $data = $digests->map(function($d) {
            $localNow = \Carbon\Carbon::now($d->timezone ?? 'UTC');
            $scheduledHM = substr($d->scheduled_at, 0, 5);
            
            // Handle different formats H:i:s or H:i
            try {
                $scheduledTime = \Carbon\Carbon::createFromFormat('H:i', $scheduledHM, $d->timezone ?? 'UTC');
                $diff = $scheduledTime->diffInMinutes($localNow, false);
            } catch (\Exception $e) {
                $diff = 'Error parsing schedule';
            }
            
            return [
                'id' => $d->id,
                'name' => $d->name,
                'user' => $d->user->email,
                'status' => $d->status,
                'is_paid' => $d->user->subscribed('youtube'),
                'timezone' => $d->timezone ?? 'UTC',
                'local_now_time' => $localNow->toDateTimeString(),
                'scheduled_at' => $scheduledHM,
                'diff_to_schedule_mins' => $diff,
                'in_run_window' => (is_numeric($diff) && $diff >= 0 && $diff <= 10),
                'last_run' => $d->last_run_at ? $d->last_run_at->toDateTimeString() : 'Never',
            ];
        });

        return response()->json([
            'message' => 'Digest Recovery Report',
            'recovered_stuck_count' => $recovered,
            'server_utc_now' => now()->toDateTimeString(),
            'active_digests' => $data
        ]);
    })->name('admin.digests.debug');

    // Dataset Recovery & Debug Route
    Route::get('/admin/datasets/debug', function() {
        $recovered = \App\Models\Dataset::where('status', 'syncing')
            ->where('updated_at', '<', now()->subMinutes(10))
            ->update(['status' => 'idle']);

        if (request()->has('reset_all')) {
            \App\Models\Dataset::query()->update(['status' => 'idle']);
        }

        $datasets = \App\Models\Dataset::with('user')->get();
        $data = $datasets->map(function($d) {
            return [
                'id' => $d->id,
                'name' => $d->name,
                'user' => $d->user->email,
                'status' => $d->status,
                'last_synced_at' => $d->last_synced_at ? $d->last_synced_at->toDateTimeString() : 'Never',
            ];
        });

        return response()->json([
            'message' => 'Dataset Recovery Report',
            'recovered_stuck_count' => $recovered,
            'server_utc_now' => now()->toDateTimeString(),
            'active_datasets' => $data
        ]);
    })->name('admin.datasets.debug');
});
