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
        // 1. Stats
        $totalUsers = User::count();
        $totalCreditsConsumed = User::sum('usage_ai_summary') + User::sum('usage_video_fetch'); // Assuming 1 credit = 1 fetch/summary
        // Actually, let's just sum daily_usage if it tracks total? No, daily resets.
        // Let's rely on usage columns.
        
        $totalVideosProcessed = Video::count();
        $recentUsers = User::latest()->take(5)->get();

        // 2. Users Pagination
        $users = User::withCount('channels')->latest()->paginate(20);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
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
