<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class GoogleAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            Log::info('Google Callback Received');
            $googleUser = Socialite::driver('google')->user();
            Log::info('Google User Retrieved: ' . $googleUser->getEmail());

            $signUpEnabled = \App\Models\Setting::where('key', 'sign_up_enabled')->value('value');
            $adminOnly = \App\Models\Setting::where('key', 'admin_only_access')->value('value');

            $user = User::where('email', $googleUser->getEmail())->first();

            if (!$user) {
                Log::info('User not found, attempting creation/check');
                if ($signUpEnabled === 'false' || $adminOnly === 'true') {
                     Log::warning('Registration blocked: Signups=' . $signUpEnabled . ', AdminOnly=' . $adminOnly);
                     return redirect()->route('login')->withErrors(['email' => 'New registrations are currently disabled.']);
                }

                // Determine service type based on domain/subdomain context if possible, 
                // or default to first available service or generic.
                // For now, we default to 'youtube' as it's the main app focus, 
                // or we could inspect the host/session.
                // But simplified: Just create user.
                
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'password' => Hash::make(Str::random(24)), // Random password
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'service_type' => 'youtube', // Default
                ]);
                Log::info('User created: ' . $user->id);
            } else {
                Log::info('User found: ' . $user->id);
                if ($adminOnly === 'true' && !$user->is_admin) {
                     Log::warning('Maintenance mode block for user: ' . $user->id);
                     return redirect()->route('login')->withErrors(['email' => 'Maintenance mode is active. Administrators only.']);
                }
                // Update Google ID if not set
                if (!$user->google_id) {
                    $user->update([
                        'google_id' => $googleUser->getId(),
                        'avatar' => $googleUser->getAvatar(),
                    ]);
                    Log::info('User Google ID updated');
                }
            }

            Auth::login($user);
            Log::info('User logged in successfully');

            return redirect()->intended(route('dashboard'));

        } catch (\Exception $e) {
            Log::error('Google Auth Failed: ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());
            return redirect()->route('login')->withErrors(['email' => 'Google Login failed. Please try again.']);
        }
    }
}
