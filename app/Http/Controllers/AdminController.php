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

        // 2. Users Pagination filtered by service
        $users = User::where('service_type', $service)
            ->withCount('channels')
            ->latest()
            ->paginate(20);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'service' => ucfirst($service),
                'totalUsers' => $totalUsers,
                'totalCreditsConsumed' => $totalCreditsConsumed,
                'totalVideosProcessed' => $totalVideosProcessed,
            ],
            'users' => $users,
        ]);
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
}
