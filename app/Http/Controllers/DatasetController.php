<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Services\YoutubeService;
use App\Services\DatasetService;
use App\Jobs\SyncDatasetJob;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DatasetController extends Controller
{
    protected $youtube;
    protected $datasetService;

    public function __construct(YoutubeService $youtube, DatasetService $datasetService)
    {
        $this->youtube = $youtube;
        $this->datasetService = $datasetService;
    }

    public function index(Request $request)
    {
        $datasets = $request->user()->datasets()
            ->withCount('videos')
            ->latest()
            ->get();

        return Inertia::render('YouTube/Datasets/Index', [
            'datasets' => $datasets,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'channel_url' => 'required|url',
            'scheduled_time' => 'required|string', // HH:mm
            'timezone' => 'required|string',
        ]);

        $dataset = $request->user()->datasets()->create([
            'name' => $validated['name'],
            'channel_url' => $validated['channel_url'],
            'scheduled_time' => $validated['scheduled_time'],
            'timezone' => $validated['timezone'],
            'status' => 'syncing',
        ]);

        // Trigger initial sync (Smart Catch-up defaults to 1 day for new datasets)
        SyncDatasetJob::dispatch($dataset, false);

        return back()->with('success', 'Dataset created and initial sync started.');
    }

    public function update(Request $request, Dataset $dataset)
    {
        $this->authorizeOwner($dataset);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'scheduled_time' => 'required|string', // HH:mm
        ]);

        $dataset->update($validated);

        return back()->with('success', 'Dataset updated.');
    }

    public function sync(Dataset $dataset, Request $request)
    {
        $this->authorizeOwner($dataset);

        // Atomic lock to prevent concurrent sync triggers (Manual or Scheduler)
        $lock = Cache::lock('sync-dataset-'.$dataset->id, 300); // 5 minute lock ceiling

        if (!$lock->get()) {
            return back()->withErrors(['error' => 'Sync already in progress or queued.']);
        }

        if ($dataset->status === 'syncing') {
            $lock->release(); 
            return back()->withErrors(['error' => 'Sync already in progress.']);
        }

        $isFullSync = $request->boolean('full_sync');
        $dataset->update(['status' => 'syncing']);
        
        SyncDatasetJob::dispatch($dataset, $isFullSync);

        $lock->release(); 

        return back()->with('success', $isFullSync ? 'Full sync triggered.' : 'Sync triggered.');
    }

    public function toggle(Dataset $dataset)
    {
        $this->authorizeOwner($dataset);
        $dataset->update(['is_paused' => !$dataset->is_paused]);

        return back()->with('success', $dataset->is_paused ? 'Dataset paused.' : 'Dataset resumed.');
    }

    public function destroy(Dataset $dataset)
    {
        $this->authorizeOwner($dataset);
        
        // Delete file if exists
        if ($dataset->file_path) {
            Storage::delete($dataset->file_path);
        }

        $dataset->delete();

        return back()->with('success', 'Dataset deleted.');
    }

    public function download(Dataset $dataset)
    {
        $this->authorizeOwner($dataset);

        if (!$dataset->file_path || !Storage::exists($dataset->file_path)) {
            return back()->withErrors(['error' => 'Knowledge file not generated yet.']);
        }

        return Storage::download($dataset->file_path, "{$dataset->name}-knowledge.md");
    }

    public function videos(Dataset $dataset, Request $request)
    {
        $this->authorizeOwner($dataset);

        $query = $dataset->videos();

        // Search logic
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('channel_title', 'like', "%{$search}%");
            });
        }

        $videos = $query->latest()
            ->select('videos.id', 'videos.video_id', 'title', 'channel_title', 'thumbnail_url', 'videos.created_at', 'duration', 'published_at')
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('YouTube/Datasets/Videos', [
            'dataset' => $dataset,
            'videos' => $videos,
            'filters' => (object) $request->only(['search']),
        ]);
    }


    protected function authorizeOwner(Dataset $dataset)
    {
        if ($dataset->user_id !== auth()->id()) {
            abort(403);
        }
    }
}
