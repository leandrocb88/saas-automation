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

class TranslateVideoSummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

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
        if (!$this->video->summary_detailed) {
            Cache::put("video_{$this->video->id}_translate_summary_status", 'failed', 300);
            $this->refund();
            return;
        }

        // Use configured AI driver
        $driver = config('services.ai.translate_driver', 'gemini');
        $aiService = $driver === 'openai' 
            ? app(\App\Services\OpenAIService::class) 
            : app(\App\Services\GeminiService::class);

        try {
            $translatedSummary = $aiService->translateSummary($this->video->summary_detailed, $this->targetLanguage);

            if (!$translatedSummary) {
                Cache::put("video_{$this->video->id}_translate_summary_status", 'failed', 300);
                $this->refund();
                return;
            }

            $translations = $this->video->translations ?? [];
            if (!isset($translations[$this->targetLanguage])) {
                $translations[$this->targetLanguage] = [];
            }
            $translations[$this->targetLanguage]['summary_detailed'] = $translatedSummary;

            // Save to DB
            $this->video->update([
                'translations' => $translations,
            ]);

            Cache::put("video_{$this->video->id}_translate_summary_status", 'completed', 300);
        } catch (\Exception $e) {
            Log::error('Background Summary Translation Job Failed: ' . $e->getMessage());
            Cache::put("video_{$this->video->id}_translate_summary_status", 'failed', 300);
            $this->refund();
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
            Log::error('Refund Failed in Summary Translation Job: ' . $e->getMessage());
        }
    }
}
