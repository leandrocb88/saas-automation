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

    public function buyCredits(Request $request)
    {
        $user = $request->user();

        // Only paid subscribers can top up credits
        if (!$user->subscribed('youtube')) {
            return redirect()->route('plans', ['service' => 'youtube'])
                ->withErrors(['error' => 'Credit top-ups are available for paid subscribers only. Please upgrade your plan first.']);
        }
        
        // Ensure you have a standard price ID or create an inline price item
        // Assuming $5 for 5000 credits as a one-time charge
        
        return $user->checkoutCharge(500, '5000 Credits Top-up', 1, [
            'success_url' => route('checkout.credits.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('billing'),
        ]);
    }

    public function creditSuccess(Request $request)
    {
        $user = $request->user();
        $sessionId = $request->query('session_id');

        if (!$sessionId) {
            return redirect()->route('billing')->withErrors(['error' => 'Invalid session.']);
        }

        try {
            // Verify session with Stripe
            $stripe = new \Stripe\StripeClient(config('cashier.secret'));
            $session = $stripe->checkout->sessions->retrieve($sessionId);

            // Double check payment status and metadata if needed
            if ($session->payment_status === 'paid') {
                // Prevent duplicate processing if possible (e.g. tracking processed session IDs)
                // For now, simpler implementation:
                
                // Add credits
                $user->increment('purchased_credits', 5000);
                
                return redirect()->route('billing')->with('success', '5000 credits successfully added to your account!');
            }
            
        } catch (\Exception $e) {
            return redirect()->route('billing')->withErrors(['error' => 'Payment verification failed.']);
        }

        return redirect()->route('billing');
    }
}
