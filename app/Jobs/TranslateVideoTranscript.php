<?php

namespace App\Jobs;

use App\Models\Video;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TranslateVideoTranscript implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes max

    protected $video;
    protected $targetLanguage;
    protected $user;
    protected $ip;
    protected $userAgent;
    protected $cost;

    /**
     * Create a new job instance.
     */
    public function __construct(Video $video, string $targetLanguage, ?User $user, ?string $ip, ?string $userAgent, int $cost)
    {
        $this->video = $video;
        $this->targetLanguage = $targetLanguage;
        $this->user = $user;
        $this->ip = $ip;
        $this->userAgent = $userAgent;
        $this->cost = $cost;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!$this->video->transcript) {
            Cache::put("video_{$this->video->id}_translate_transcript_status", 'failed', 300);
            $this->refund();
            return;
        }

        // Use configured AI driver
        $driver = config('services.ai.translate_driver', 'gemini');
        $aiService = $driver === 'openai' 
            ? app(\App\Services\OpenAIService::class) 
            : app(\App\Services\GeminiService::class);

        try {
            $translatedTranscript = $aiService->translateTranscript($this->video->transcript, $this->targetLanguage);

            if (!$translatedTranscript) {
                Cache::put("video_{$this->video->id}_translate_transcript_status", 'failed', 300);
                $this->refund();
                return;
            }

            $translations = $this->video->translations ?? [];
            if (!isset($translations[$this->targetLanguage])) {
                $translations[$this->targetLanguage] = [];
            }
            $translations[$this->targetLanguage]['transcript'] = $translatedTranscript;

            // Save to DB
            $this->video->update([
                'translations' => $translations,
            ]);

            Cache::put("video_{$this->video->id}_translate_transcript_status", 'completed', 300);
        } catch (\Exception $e) {
            Log::error('Background Translation Job Failed: ' . $e->getMessage());
            Cache::put("video_{$this->video->id}_translate_transcript_status", 'failed', 300);
            $this->refund();
            // Don't throw, we want it to gracefully fail and notify the frontend
        }
    }

    protected function refund()
    {
        try {
            $quotaManager = app(\App\Services\QuotaManager::class);
            if ($this->user) {
                $quotaManager->decrementUsage($this->user, 'youtube', $this->cost);
            } elseif ($this->ip && $this->userAgent) {
                $quotaManager->decrementGuestUsage($this->ip, $this->userAgent, 'youtube', $this->cost);
            }
        } catch (\Exception $e) {
            Log::error('Refund Failed in Translation Job: ' . $e->getMessage());
        }
    }
}
