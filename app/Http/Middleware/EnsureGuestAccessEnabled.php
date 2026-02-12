<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class EnsureGuestAccessEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. If user is authenticated, allow access always
        if (Auth::check()) {
            return $next($request);
        }

        // 2. Check system setting
        $guestAccess = \App\Models\Setting::where('key', 'guest_access_enabled')->value('value');

        // Default to 'true' if not set
        if ($guestAccess === 'false') {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Guest access is currently disabled.'], 403);
            }
            // Redirect to login with a flash message
            return redirect()->route('login')->with('error', 'Guest access is currently disabled. Please log in.');
        }

        return $next($request);
    }
}
