<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Video;

class GenerateVideoPdf implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Video $video)
    {
        //
    }

    public function handle(): void
    {
        try {
            $this->video->update(['pdf_status' => 'processing']);

            $pdf = Pdf::loadView('pdfs.video', ['video' => $this->video]);
            
            $filename = 'video-' . $this->video->id . '-' . time() . '.pdf';
            $path = 'videos/pdfs/' . $filename;
            
            Storage::disk(config('filesystems.default', 'public'))->put($path, $pdf->output());

            $this->video->update([
                'pdf_path' => $path,
                'pdf_status' => 'completed',
            ]);
        } catch (\Exception $e) {
            Log::error("Video PDF Generation Failed: " . $e->getMessage());
            $this->video->update(['pdf_status' => 'failed']);
            throw $e;
        }
    }
}
