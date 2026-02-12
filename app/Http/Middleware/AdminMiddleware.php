<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->isAdmin()) {
            $host = $request->getHost();
            $service = str_contains($host, 'zillow') ? 'zillow' : 'youtube';
            
            if ($request->user()->service_type === $service) {
                return $next($request);
            }
        }

        abort(403, 'Unauthorized access.');
    }
}
