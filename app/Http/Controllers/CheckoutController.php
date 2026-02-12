<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function subscription(Request $request, string $plan)
    {
        $user = $request->user();
        $service = $user->service_type; // 'youtube' or 'zillow'
        $period = $request->query('period', 'monthly'); // 'monthly' or 'yearly'
        
        // 1. Get Price ID from Config
        // Config structure: plans.service.plan.prices.period
        $priceId = config("plans.{$service}.{$plan}.prices.{$period}");

        if (!$priceId) {
            return back()->withErrors(['error' => 'Invalid plan or price not configured.']);
        }

        // 2. Start Stripe Checkout Session (Cashier)
        return $user->newSubscription($service, $priceId)
            ->checkout([
                'success_url' => route('dashboard'),
                'cancel_url' => route('plans'),
            ]);
    }

    public function cancel(Request $request)
    {
        $user = $request->user();
        $service = $user->service_type;

        if ($user->subscribed($service)) {
            $user->subscription($service)->cancel();
        }
        
        // Get the end date (it might be 'ends_at' on the subscription model)
        $endDate = $user->subscription($service)->ends_at;

        return back()->with('message', 'Subscription cancelled. Your plan will remain active until ' . $endDate->format('F j, Y') . '.');
    }

    public function resume(Request $request)
    {
        $user = $request->user();
        $service = $user->service_type;

        if ($user->subscribed($service) && $user->subscription($service)->onGracePeriod()) {
            $user->subscription($service)->resume();
        }

        return back()->with('message', 'Subscription resumed. Welcome back!');
    }
}
