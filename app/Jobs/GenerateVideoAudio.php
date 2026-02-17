<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Video;
use App\Services\OpenAIService;
use App\Services\QuotaManager;

class GenerateVideoAudio implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Video $video)
    {
        //
    }

    public function handle(OpenAIService $openAI, QuotaManager $quotaManager): void
    {
        try {
            $this->video->update(['audio_status' => 'processing']);

            $user = $this->video->user;
            // Cost is 1 credit per video audio generation? Or maybe 0 if it's included?
            // Let's assume 1 credit for now to be safe, or 0 if we want to be generous.
            // effectively this is "re-purposing" the content. 
            // Let's charge 1 credit for the TTS service usage.
            $cost = 1;

            if (!$quotaManager->checkQuota($user, 'youtube', $cost)) {
                Log::warning("User {$user->id} has insufficient credits for video audio generation.");
                $this->video->update(['audio_status' => 'failed']);
                return;
            }

            $quotaManager->incrementUsage($user, 'youtube', $cost, 'audio_video');

            try {
                $summary = $this->video->summary_short ?? $this->video->summary_detailed;
                $cleanSummary = strip_tags(Str::markdown($summary ?? ''));
                $text = "Audio for: " . $this->video->title . ". \n\n" . $cleanSummary;

                $audio = $openAI->generateAudio($text);

                if ($audio) {
                    $filename = 'video_' . $this->video->id . '_' . time() . '.mp3';
                    $path = 'videos/audio/' . $filename;
                    
                    Storage::disk(config('filesystems.default', 'public'))->put($path, $audio);

                    // Calculate duration using getID3
                    $duration = 0;
                    try {
                        $tempPath = tempnam(sys_get_temp_dir(), 'mp3');
                        file_put_contents($tempPath, $audio); // Use $audio for content
                        
                        $getID3 = new \getID3;
                        $fileInfo = $getID3->analyze($tempPath);
                        
                        if (isset($fileInfo['playtime_seconds'])) {
                            $duration = round($fileInfo['playtime_seconds']);
                        }
                        
                        unlink($tempPath);
                    } catch (\Throwable $e) {
                        // Log error but don't fail the job just for duration
                        \Illuminate\Support\Facades\Log::warning("Failed to calculate audio duration for video {$this->video->id}: " . $e->getMessage());
                    }
                    
                    $this->video->update([
                        'audio_path' => $path,
                        'audio_status' => 'completed',
                        'audio_duration' => $duration // Added audio_duration
                    ]);
                } else {
                     $quotaManager->decrementUsage($user, 'youtube', $cost);
                     $this->video->update(['audio_status' => 'failed']);
                }

            } catch (\Throwable $e) {
                $quotaManager->decrementUsage($user, 'youtube', $cost);
                throw $e;
            }
        } catch (\Throwable $e) {
            Log::error("Video Audio Generation Failed: " . $e->getMessage());
            $this->video->update(['audio_status' => 'failed']);
            throw $e;
        }
    }
}
