<?php

namespace App\Http\Controllers;

use App\Models\Digest;
use App\Models\Channel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class DigestController extends Controller
{
    public function index()
    {
        $digests = Auth::user()->digests()->withCount('channels')->get();
        return Inertia::render('Digests/Index', [
            'digests' => $digests,
        ]);
    }

    public function create()
    {
        $channels = Auth::user()->channels()->select('id', 'name', 'thumbnail_url')->get();
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
        ]);

        $digest = Auth::user()->digests()->create([
            'name' => $validated['name'],
            'frequency' => $validated['frequency'],
            'scheduled_at' => $validated['scheduled_at'],
            'day_of_week' => $validated['day_of_week'],
            'mode' => $validated['mode'],
            'search_term' => $validated['search_term'],
            'custom_prompt' => $validated['custom_prompt'],
            'is_active' => true,
        ]);

        if (!empty($validated['channel_ids'])) {
            $digest->channels()->attach($validated['channel_ids']);
        }

        return redirect()->route('digests.index')->with('success', 'Digest created successfully.');
    }

    public function edit(Digest $digest)
    {
        if ($digest->user_id !== Auth::id()) abort(403);

        $channels = Auth::user()->channels()->select('id', 'name', 'thumbnail_url')->get();
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
            'is_active' => 'boolean',
        ]);

        $digest->update([
            'name' => $validated['name'],
            'frequency' => $validated['frequency'],
            'scheduled_at' => $validated['scheduled_at'],
            'day_of_week' => $validated['day_of_week'],
            'mode' => $validated['mode'],
            'search_term' => $validated['search_term'],
            'custom_prompt' => $validated['custom_prompt'],
            'is_active' => $validated['is_active'] ?? $digest->is_active,
        ]);

        if (isset($validated['channel_ids'])) {
            $digest->channels()->sync($validated['channel_ids']);
        }

        return redirect()->route('digests.index')->with('success', 'Digest updated successfully.');
    }

    public function destroy(Digest $digest)
    {
        if ($digest->user_id !== Auth::id()) abort(403);
        $digest->delete();
        return redirect()->route('digests.index')->with('success', 'Digest deleted.');
    }
}
