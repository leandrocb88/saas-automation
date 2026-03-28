<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Video;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function index()
    {
        $host = request()->getHost();
        $service = str_contains($host, 'zillow') ? 'zillow' : 'youtube';

        // 1. Stats filtered by service
        $totalUsers = User::where('service_type', $service)->count();
        $totalCreditsConsumed = User::where('service_type', $service)->sum('daily_usage'); // Using daily_usage as a proxy for activity
        
        $totalVideosProcessed = ($service === 'youtube') ? Video::count() : 0;
        $recentUsers = User::where('service_type', $service)->latest()->take(5)->get();
        
        $guestAccess = \App\Models\Setting::where('key', 'guest_access_enabled')->value('value');
        $signUpEnabled = \App\Models\Setting::where('key', 'sign_up_enabled')->value('value');
        $adminOnly = \App\Models\Setting::where('key', 'admin_only_access')->value('value');

        // 2. Users Pagination filtered by service
        $usersPagination = User::where('service_type', $service)
            ->withCount('channels')
            ->latest()
            ->paginate(20);

        /** @var \App\Services\QuotaManager $quotaManager */
        $quotaManager = app(\App\Services\QuotaManager::class);

        $usersPagination->getCollection()->transform(function ($user) use ($quotaManager, $service) {
            $user->total_credits = $quotaManager->getRemainingQuota($user, $service);
            return $user;
        });

        $users = $usersPagination;

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'service' => ucfirst($service),
                'totalUsers' => $totalUsers,
                'totalCreditsConsumed' => $totalCreditsConsumed,
                'totalVideosProcessed' => $totalVideosProcessed,
                'guestAccess' => $guestAccess === 'false' ? false : true,
                'signUpEnabled' => $signUpEnabled === 'false' ? false : true,
                'adminOnly' => $adminOnly === 'true' ? true : false,
            ],
            'users' => $users,
        ]);
    }
    public function store(Request $request)
    {
        $host = $request->getHost();
        $service = str_contains($host, 'zillow') ? 'zillow' : 'youtube';

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,NULL,id,service_type,' . $service,
            'password' => 'required|string|min:8',
            'is_admin' => 'boolean',
            'purchased_credits' => 'nullable|integer|min:0',
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
            'service_type' => $service,
            'is_admin' => $validated['is_admin'] ?? false,
            'purchased_credits' => $validated['purchased_credits'] ?? 0,
        ]);

        return back()->with('success', 'User created successfully.');
    }

    public function destroy(User $user)
    {
        // Prevent deleting self
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'You cannot delete yourself.']);
        }

        $user->delete();

        return back()->with('success', 'User deleted successfully.');
    }

    public function toggleBlock(User $user)
    {
        // Prevent blocking self
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'You cannot block yourself.']);
        }

        $user->update([
            'is_blocked' => !$user->is_blocked,
        ]);

        return back();
    }

    public function resetCredits(User $user)
    {
        $user->update(['daily_usage' => 0]);
        return back()->with('success', 'User credits reset successfully.');
    }

    public function update(Request $request, User $user)
    {
        $host = $request->getHost();
        $service = str_contains($host, 'zillow') ? 'zillow' : 'youtube';

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id . ',id,service_type,' . $service,
            'password' => 'nullable|string|min:8',
            'is_admin' => 'boolean',
            'purchased_credits' => 'nullable|integer|min:0',
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_admin' => $validated['is_admin'] ?? false,
            'purchased_credits' => $validated['purchased_credits'] ?? 0,
        ];

        if (!empty($validated['password'])) {
            $data['password'] = \Illuminate\Support\Facades\Hash::make($validated['password']);
        }

        // Prevent self-demotion if they are the only admin, or just generally prevent editing own admin status to false via this UI if desired?
        // For now, let's just allow it but maybe prevent removing OWN admin access if it's the current user?
        if ($user->id === auth()->id() && isset($validated['is_admin']) && !$validated['is_admin']) {
             return back()->withErrors(['error' => 'You cannot remove your own admin privileges.']);
        }

        $user->update($data);

        return back()->with('success', 'User updated successfully.');
    }
    public function toggleGuestAccess()
    {
        $setting = \App\Models\Setting::firstOrCreate(
            ['key' => 'guest_access_enabled'],
            ['group' => 'general']
        );

        $currentValue = $setting->value === 'false' ? false : true;
        // Toggle logic: if true -> false, if false -> true
        $setting->value = $currentValue ? 'false' : 'true';
        $setting->save();

        return back()->with('success', 'Guest access has been ' . ($setting->value === 'true' ? 'enabled' : 'disabled') . '.');
    }

    public function toggleRegistration()
    {
        $setting = \App\Models\Setting::firstOrCreate(
            ['key' => 'sign_up_enabled'],
            ['group' => 'general', 'value' => 'true']
        );

        $currentValue = $setting->value === 'false' ? false : true;
        $setting->value = $currentValue ? 'false' : 'true';
        $setting->save();

        return back()->with('success', 'Sign-ups have been ' . ($setting->value === 'true' ? 'enabled' : 'disabled') . '.');
    }

    public function toggleAdminOnly()
    {
        $setting = \App\Models\Setting::firstOrCreate(
            ['key' => 'admin_only_access'],
            ['group' => 'general', 'value' => 'false']
        );

        $currentValue = $setting->value === 'true' ? true : false;
        $setting->value = $currentValue ? 'false' : 'true';
        $setting->save();

        return back()->with('success', 'Admin-only access has been ' . ($setting->value === 'true' ? 'enabled' : 'disabled') . '.');
    }
}
