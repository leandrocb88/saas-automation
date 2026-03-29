<?php

namespace App\Http\Controllers;

use App\Models\Digest;
use App\Models\Channel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class DigestController extends Controller
{
    public function index(Request $request)
    {
        $digests = Auth::user()->digests()->withCount('channels')->latest()->paginate(12);
        $service = str_contains($request->getHost(), 'zillow') ? 'zillow' : 'youtube';
        return Inertia::render('Digests/Index', [
            'digests' => $digests,
            'isPaid' => Auth::user()->subscribed($service),
        ]);
    }

    public function create()
    {
        $channels = Auth::user()->channels()->select('id', 'name', 'thumbnail_url', 'is_paused')->orderBy('name')->get();
        return Inertia::render('Digests/Create', [
            'availableChannels' => $channels,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'frequency' => 'required|in:daily,weekly',
            'scheduled_at' => 'required|date_format:H:i',
            'day_of_week' => 'nullable|required_if:frequency,weekly|in:mon,tue,wed,thu,fri,sat,sun',
            'mode' => 'required|in:channels,search_term,mixed',
            'search_term' => 'nullable|required_if:mode,search_term',
            'channel_ids' => 'nullable|array',
            'channel_ids.*' => 'exists:channels,id',
            'custom_prompt' => 'nullable|string',
            'global_summary_prompt' => 'nullable|string',
            'timezone' => 'required|string|timezone',
            'video_types' => 'nullable|array',
            'video_types.*' => 'in:videos,shorts,streams',
        ]);

        $service = str_contains($request->getHost(), 'zillow') ? 'zillow' : 'youtube';
        if (!Auth::user()->subscribed($service)) {
            $activeCount = Auth::user()->digests()->where('is_active', true)->count();
            if ($activeCount >= 1) {
                return redirect()->back()->with('error', 'Free users can only have 1 active digest. Please upgrade or pause your existing digest first.');
            }
        }

        $digest = Auth::user()->digests()->create([
            'name' => $validated['name'],
            'frequency' => $validated['frequency'],
            'scheduled_at' => $validated['scheduled_at'],
            'day_of_week' => $validated['day_of_week'],
            'mode' => $validated['mode'],
            'search_term' => $validated['search_term'],
            'custom_prompt' => $validated['custom_prompt'],
            'global_summary_prompt' => $validated['global_summary_prompt'],
            'timezone' => $validated['timezone'] ?? 'UTC',
            'video_types' => !empty($validated['video_types']) ? $validated['video_types'] : ['videos'],
            'is_active' => true,
            'last_time_change_at' => now(), // Initial setup counts as a change
        ]);

        if (!empty($validated['channel_ids'])) {
            $digest->channels()->attach($validated['channel_ids']);
        }

        $message = 'Digest created successfully.';
        $service = str_contains($request->getHost(), 'zillow') ? 'zillow' : 'youtube';
        if (!Auth::user()->subscribed($service)) {
            $hasRunToday = Auth::user()->digests()->whereNotNull('last_run_at')->get()->contains(function($d) {
                $dLocalRun = \Carbon\Carbon::parse($d->last_run_at)->timezone($d->timezone ?? 'UTC');
                return $dLocalRun->isSameDay(\Carbon\Carbon::now($d->timezone ?? 'UTC'));
            });
            if ($hasRunToday) {
                $message .= ' Changes will apply the next day, as this digest has already been processed today.';
            }
        }

        return redirect()->route('digests.index')->with('success', $message);
    }

    public function edit(Digest $digest)
    {
        if ($digest->user_id !== Auth::id()) abort(403);

        $channels = Auth::user()->channels()->select('id', 'name', 'thumbnail_url', 'is_paused')->orderBy('name')->get();
        $digest->load('channels');

        return Inertia::render('Digests/Edit', [
            'digest' => $digest,
            'availableChannels' => $channels,
        ]);
    }

    public function update(Request $request, Digest $digest)
    {
        if ($digest->user_id !== Auth::id()) abort(403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'frequency' => 'required|in:daily,weekly',
            'scheduled_at' => 'required|date_format:H:i',
            'day_of_week' => 'nullable|required_if:frequency,weekly|in:mon,tue,wed,thu,fri,sat,sun',
            'mode' => 'required|in:channels,search_term,mixed',
            'search_term' => 'nullable|required_if:mode,search_term',
            'channel_ids' => 'nullable|array',
            'channel_ids.*' => 'exists:channels,id',
            'custom_prompt' => 'nullable|string',
            'global_summary_prompt' => 'nullable|string',
            'is_active' => 'boolean',
            'timezone' => 'required|string|timezone',
            'video_types' => 'nullable|array',
            'video_types.*' => 'in:videos,shorts,streams',
        ]);

        $timeChanged = $validated['scheduled_at'] !== $digest->scheduled_at;

        $digest->update([
            'name' => $validated['name'],
            'frequency' => $validated['frequency'],
            'scheduled_at' => $validated['scheduled_at'],
            'day_of_week' => $validated['day_of_week'],
            'mode' => $validated['mode'],
            'search_term' => $validated['search_term'],
            'custom_prompt' => $validated['custom_prompt'],
            'global_summary_prompt' => $validated['global_summary_prompt'],
            'is_active' => $validated['is_active'] ?? $digest->is_active,
            'timezone' => $validated['timezone'] ?? $digest->timezone,
            'video_types' => !empty($validated['video_types']) ? $validated['video_types'] : ['videos'],
            'last_time_change_at' => $timeChanged ? now() : $digest->last_time_change_at,
        ]);

        if (isset($validated['channel_ids'])) {
            $digest->channels()->sync($validated['channel_ids']);
        }

        $message = 'Digest updated successfully.';
        $service = str_contains($request->getHost(), 'zillow') ? 'zillow' : 'youtube';
        $isPaid = Auth::user()->subscribed($service);
        $settingActive = $validated['is_active'] ?? $digest->is_active;

        if (!$isPaid) {
            $hasRunToday = Auth::user()->digests()->whereNotNull('last_run_at')->get()->contains(function($d) {
                $dLocalRun = \Carbon\Carbon::parse($d->last_run_at)->timezone($d->timezone ?? 'UTC');
                return $dLocalRun->isSameDay(\Carbon\Carbon::now($d->timezone ?? 'UTC'));
            });
            if ($hasRunToday) {
                $message .= ' Changes will apply the next day, as this digest has already been processed today.';
            }
        }

        return redirect()->route('digests.index')->with('success', $message);
    }

    public function destroy(Digest $digest)
    {
        if ($digest->user_id !== Auth::id()) abort(403);
        $digest->delete();
        return redirect()->route('digests.index')->with('success', 'Digest deleted.');
    }
}
