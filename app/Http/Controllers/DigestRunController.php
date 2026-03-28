<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\DigestRun;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DigestRunController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return \Inertia\Inertia::render('DigestRuns/Index', [
                'runs' => [
                    'data' => [],
                    'links' => [],
                    'total' => 0,
                ],
                'digestId' => $request->digest_id,
            ]);
        }

        $query = $user->digestRuns()->with('digest')->latest();

        if ($request->has('digest_id')) {
            $query->where('digest_id', $request->digest_id);
        }

        $runs = $query->paginate(20);

        return \Inertia\Inertia::render('DigestRuns/Index', [
            'runs' => $runs,
            'digestId' => $request->digest_id,
        ]);
    }

    public function downloadPdf(DigestRun $digestRun)
    {
        if ($digestRun->user_id !== Auth::id()) abort(403);

        // Check status
        if ($digestRun->pdf_status === 'completed' && $digestRun->pdf_path && Storage::disk(config('filesystems.default', 'public'))->exists($digestRun->pdf_path)) {
            return Storage::disk(config('filesystems.default', 'public'))->download($digestRun->pdf_path);
        }

        if ($digestRun->pdf_status === 'processing') {
            return response()->json(['status' => 'processing']);
        }

        // Needs generation (pending or failed or missing file)
        $digestRun->update(['pdf_status' => 'processing']);
        \App\Jobs\GenerateDigestPdf::dispatch($digestRun);
        
        return response()->json(['status' => 'processing']);
    }

    public function downloadAudio(DigestRun $digestRun)
    {
        if ($digestRun->user_id !== Auth::id()) abort(403);

        // Check status
        if ($digestRun->audio_status === 'completed' && $digestRun->audio_path && Storage::disk(config('filesystems.default', 'public'))->exists($digestRun->audio_path)) {
            return Storage::disk(config('filesystems.default', 'public'))->download($digestRun->audio_path);
        }

        if ($digestRun->audio_status === 'processing') {
             return response()->json(['status' => 'processing']);
        }

        // Needs generation
        $digestRun->update(['audio_status' => 'processing']);
        \App\Jobs\GenerateDigestAudio::dispatch($digestRun);
        
        return response()->json(['status' => 'processing']);
    }

    public function status(DigestRun $digestRun)
    {
        if ($digestRun->user_id !== Auth::id()) abort(403);

        return response()->json([
            'pdf_status' => $digestRun->pdf_status,
            'audio_status' => $digestRun->audio_status,
            'audio_duration' => $digestRun->audio_duration,
            'pdf_url' => $digestRun->pdf_status === 'completed' ? route('digest_runs.pdf', $digestRun->id) : null,
            'audio_url' => $digestRun->audio_status === 'completed' ? route('digest_runs.audio', $digestRun->id) : null,
        ]);
    }
}
