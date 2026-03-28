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

    public $timeout = 300;

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
    public function handle(OpenAIService $openAI, GeminiService $gemini, \App\Services\QuotaManager $quotaManager): void
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
                $this->refundCredit($quotaManager);
                return;
            }

            $provider = config('services.ai.provider', 'gemini');
            $summary = null;

            if ($provider === 'gemini') {
                Log::info("Generating summary with Gemini for video {$this->video->video_id}");
                $summary = $gemini->generateSummary($fullText, $this->type);
            } else {
                $openAIModel = config('services.openai.summary_model', 'gpt-5-nano');
                Log::info("Generating summary with OpenAI ({$openAIModel}) for video {$this->video->video_id}");
                $summary = $openAI->generateSummary($fullText, $this->type);
            }

            $column = $this->type === 'short' ? 'summary_short' : 'summary_detailed';
            
            $this->video->update([
                $column => $summary,
                'summary_status' => 'completed'
            ]);

        } catch (\Exception $e) {
            Log::error("Video Summary Generation Failed", [
                'video_id' => $this->video->id,
                'provider' => config('services.ai.provider', 'gemini'),
                'model' => $provider === 'gemini' ? config('services.google.gemini_summary_model') : config('services.openai.summary_model'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->video->update(['summary_status' => 'failed']);
            $this->refundCredit($quotaManager);
            throw $e;
        }
    }

    protected function refundCredit(\App\Services\QuotaManager $quotaManager): void
    {
        $cost = $quotaManager->getCost($this->video->user, 'youtube', 'ai_summary');
        
        if ($this->video->user_id) {
            $quotaManager->decrementUsage($this->video->user, 'youtube', $cost);
            Log::info("Refunded summary credit to user {$this->video->user_id} for video {$this->video->id}");
        } elseif ($this->video->guest_ip && $this->video->guest_ua) {
            $quotaManager->decrementGuestUsage($this->video->guest_ip, $this->video->guest_ua, 'youtube', $cost);
            Log::info("Refunded summary credit to guest {$this->video->guest_ip} for video {$this->video->id}");
        } else {
            Log::warning("Refund skipped: No user or guest identifiers found for video {$this->video->id}");
        }
    }
}
