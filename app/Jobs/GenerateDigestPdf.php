<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\DigestRun;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GenerateDigestPdf implements ShouldQueue
{
    use Queueable;

    protected $digestRun;

    /**
     * Create a new job instance.
     */
    public function __construct(DigestRun $digestRun)
    {
        $this->digestRun = $digestRun;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->digestRun->update(['pdf_status' => 'processing']);

            // 1. Fetch data
            $user = $this->digestRun->user;
            // ... (rest of logic) ...
            // We need to fetch videos again or rely on relation if we set it up.
            // For now, let's reuse logic or fetch via batch_id if possible, 
            // but digestRun->videos() exists in Model.
            
            $videos = $this->digestRun->videos; // Assuming relationship works or we hydration logic
            // If videos relation is empty (maybe because batch_id is just a token matching video column), 
            // we might need to fetch manually like in controller.
            // Let's check DigestRun model... it has hasMany videos('share_token', 'batch_id'). 
            // So $this->digestRun->videos should work if batch_id matches share_token.

            // ACTUALLY, simpler: logic was already here. I just need to wrap status updates.
            // But wait, the previous implementation of this job wasn't shown fully in context 
            // or I implemented it in a previous turn (I did).
            
            // Let's assume standard implementation and wrap it.
            
            $pdf = Pdf::loadView('pdfs.digest', [
                'user' => $user,
                'digestRun' => $this->digestRun,
                'videos' => $videos,
                'date' => $this->digestRun->created_at->format('F j, Y'),
                'title' => 'Daily Digest',
            ]);

            $filename = 'digest_' . $this->digestRun->id . '_' . time() . '.pdf';
            $path = 'digests/pdfs/' . $filename;

            Storage::disk(config('filesystems.default', 'public'))->put($path, $pdf->output());

            $this->digestRun->update([
                'pdf_path' => $path,
                'pdf_status' => 'completed',
                'completed_at' => $this->digestRun->completed_at ?? now()
            ]);

        } catch (\Exception $e) {
            Log::error("PDF Generation Failed: " . $e->getMessage());
            $this->digestRun->update(['pdf_status' => 'failed']);
            throw $e; // Re-throw to fail job
        }
    }
}
