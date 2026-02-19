<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use App\Models\Video;
use App\Services\OpenAIService;
use App\Services\GeminiService;

class GenerateVideoSummary implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Video $video,
        public string $type = 'detailed'
    ) {}

    /**
     * Execute the job.
     */
    public function handle(OpenAIService $openAI, GeminiService $gemini): void
    {
        try {
            $this->video->update(['summary_status' => 'processing']);

            // Construct full transcript text
            $fullText = '';
            if (is_array($this->video->transcript)) {
                $fullText = collect($this->video->transcript)->pluck('text')->join(' ');
            }

            if (empty($fullText)) {
                Log::warning("Video summary generation skipped: Empty transcript", ['video_id' => $this->video->id]);
                $this->video->update(['summary_status' => 'failed']);
                return;
            }

            $provider = config('services.ai.provider', 'openai');
            $summary = null;

            if ($provider === 'gemini') {
                Log::info("Generating summary with Gemini for video {$this->video->video_id}");
                $summary = $gemini->generateSummary($fullText, $this->type);
            } else {
                Log::info("Generating summary with OpenAI (GPT-5 Nano) for video {$this->video->video_id}");
                $summary = $openAI->generateSummary($fullText, $this->type);
            }

            $column = $this->type === 'short' ? 'summary_short' : 'summary_detailed';
            
            $this->video->update([
                $column => $summary,
                'summary_status' => 'completed'
            ]);

        } catch (\Exception $e) {
            Log::error("Video Summary Generation Failed: " . $e->getMessage(), [
                'video_id' => $this->video->id,
                'error' => $e->getMessage()
            ]);
            $this->video->update(['summary_status' => 'failed']);
            throw $e;
        }
    }
}
