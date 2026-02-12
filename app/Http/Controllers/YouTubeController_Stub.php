<?php

namespace App\Http\Controllers;

use App\Services\QuotaManager;
use App\Models\Video;
use App\Models\Channel;
use App\Models\DigestSchedule;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class YouTubeController extends Controller
{
    // ... existing index, channel, processChannel methods ...
    public function index()
    {
        return Inertia::render('YouTube/Home');
    }

    public function channel(Request $request)
    {
        $user = $request->user();
        $canSubmit = $user && $user->subscribed('youtube');

        return Inertia::render('YouTube/ChannelAnalysis', [
            'canSubmit' => $canSubmit,
        ]);
    }
    
    // --- New Subscription / Digest Methods ---

    public function subscriptions(Request $request)
    {
        $user = $request->user();
        
        return Inertia::render('YouTube/Subscriptions', [
            'channels' => $user->channels()->get(),
            'schedule' => $user->digestSchedule()->first() ?? ['preferred_time' => '09:00', 'timezone' => 'UTC', 'is_active' => true],
        ]);
    }

    public function storeSubscription(Request $request)
    {
        $request->validate([
            'url' => 'required|url',
            'name' => 'nullable|string',
        ]);

        $user = $request->user();
        
        $channel = $user->channels()->create([
            'url' => $request->url,
            'name' => $request->name ?? 'New Channel', // Ideally fetch name from URL or metadata
        ]);

        return back()->with('success', 'Channel Subscribed.');
    }

    public function destroySubscription(Request $request, Channel $channel)
    {
        if ($request->user()->id !== $channel->user_id) abort(403);
        $channel->delete();
        return back()->with('success', 'Unsubscribed.');
    }

    public function updateSchedule(Request $request)
    {
        $request->validate([
            'preferred_time' => 'required',
            'timezone' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $user = $request->user();
        
        $user->digestSchedule()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'preferred_time' => $request->preferred_time,
                'timezone' => $request->timezone,
                'is_active' => $request->is_active,
            ]
        );

        return back()->with('success', 'Schedule updated.');
    }

    public function digest(Request $request)
    {
        $user = $request->user();
        
        // Group digest videos by date
        $digests = Video::where('user_id', $user->id)
            ->where('source', 'digest')
            ->whereNotNull('digest_date')
            ->orderBy('digest_date', 'desc')
            ->get()
            ->groupBy('digest_date'); // Returns Collection of Collections

        // Transform for UI
        $formattedDigests = [];
        foreach ($digests as $date => $videos) {
             $formattedDigests[] = [
                 'date' => Carbon::parse($date)->format('F j, Y'),
                 'videos' => $videos->map(function($v) {
                     return $this->formatVideoForView($v);
                 })->values(),
             ];
        }

        return Inertia::render('YouTube/Digest', [
            'digests' => $formattedDigests,
        ]);
    }


    // ... existing methods (process, processChannel, destroy, clearHistory, history, show, generateSummary, parseVideoData, extractVideoId, getGuestId, formatVideoForView) ...
    
    // NOTE: Keep existing methods below. I'm providing a partial replacement logic here or full file. 
    // Since I can't see the full file content reliably in one go to replace everything without risk, 
    // I will use replace_file_content to inject these new methods.
}
