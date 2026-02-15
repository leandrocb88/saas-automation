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
            $googleUser = Socialite::driver('google')->user();

            $signUpEnabled = \App\Models\Setting::where('key', 'sign_up_enabled')->value('value');
            $adminOnly = \App\Models\Setting::where('key', 'admin_only_access')->value('value');

            $user = User::where('email', $googleUser->getEmail())->first();

            if (!$user) {
                if ($signUpEnabled === 'false' || $adminOnly === 'true') {
                     return redirect()->route('login')->withErrors(['email' => 'New registrations are currently disabled.']);
                }

                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'password' => Hash::make(Str::random(24)), // Random password
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'service_type' => 'youtube', // Default
                ]);
            } else {
                if ($adminOnly === 'true' && !$user->is_admin) {
                     return redirect()->route('login')->withErrors(['email' => 'Maintenance mode is active. Administrators only.']);
                }
                // Update Google ID if not set
                if (!$user->google_id) {
                    $user->update([
                        'google_id' => $googleUser->getId(),
                        'avatar' => $googleUser->getAvatar(),
                    ]);
                }
            }

            Auth::login($user);

            return redirect()->intended(route('dashboard'));

        } catch (\Exception $e) {
            Log::error('Google Auth Failed: ' . $e->getMessage());
            return redirect()->route('login')->withErrors(['email' => 'Google Login failed. Please try again.']);
        }
    }
}
