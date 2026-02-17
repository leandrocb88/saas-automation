<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\DigestRun;
use App\Services\OpenAIService;
use App\Services\QuotaManager;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class GenerateDigestAudio implements ShouldQueue
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
    public function handle(OpenAIService $openAI, QuotaManager $quotaManager): void
    {
        try {
            $this->digestRun->update(['audio_status' => 'processing']);

            $user = $this->digestRun->user;
            $videos = $this->digestRun->videos;
            $cost = $videos->count();

            // 1. Check Quota
            if (!$quotaManager->checkQuota($user, 'youtube', $cost)) {
                Log::warning("User {$user->id} has insufficient credits for audio digest generation.");
                $this->digestRun->update(['audio_status' => 'failed']);
                return;
            }

            // 2. Deduct Quota
            $quotaManager->incrementUsage($user, 'youtube', $cost, 'audio_digest');

            $tempFiles = [];
            $hasAudio = false;

            try {
                // Intro
                $introText = "Here is your digest for " . ($this->digestRun->digest->name ?? 'today') . ".";
                $audio = $openAI->generateAudio($introText);
                if ($audio) {
                    $path = tempnam(sys_get_temp_dir(), 'digest_audio_');
                    file_put_contents($path, $audio);
                    $tempFiles[] = $path;
                    $hasAudio = true;
                }

                foreach ($videos as $video) {
                    $summary = $video->summary_short ?? $video->summary_detailed;
                    $cleanSummary = strip_tags(Str::markdown($summary ?? ''));
                    $text = "Next video: " . $video->title . ". \n" . $cleanSummary;
                    
                    // Dynamic Voice Selection
                    $voice = null;
                    if (config('services.audio.driver') === 'kokoro') {
                        $lang = $this->detectLanguage($cleanSummary . ' ' . $video->title);
                        $voiceMap = config('services.audio.kokoro.voices', []);
                        $voice = $voiceMap[$lang] ?? config('services.audio.kokoro.voice', 'am_michael');
                    }

                    $audio = $openAI->generateAudio($text, $voice);
                    if ($audio) {
                        $path = tempnam(sys_get_temp_dir(), 'digest_audio_');
                        file_put_contents($path, $audio);
                        $tempFiles[] = $path;
                        $hasAudio = true;
                    }
                }
                
                if ($hasAudio) {
                     $filename = 'digest_' . $this->digestRun->id . '_' . time() . '.mp3';
                     $finalPath = 'digests/audio/' . $filename;
                     
                     // Simple concatenation for MP3
                     $finalContent = '';
                     foreach ($tempFiles as $file) {
                         $finalContent .= file_get_contents($file);
                     }
                     
                     Storage::disk(config('filesystems.default', 'public'))->put($finalPath, $finalContent);
                     
                     // Calculate duration
                     $duration = 0;
                     try {
                         $tempPath = tempnam(sys_get_temp_dir(), 'mp3');
                         file_put_contents($tempPath, $finalContent);
                         
                         $getID3 = new \getID3;
                         $fileInfo = $getID3->analyze($tempPath);
                         
                         if (isset($fileInfo['playtime_seconds'])) {
                             $duration = round($fileInfo['playtime_seconds']);
                         }
                         
                         unlink($tempPath);
                     } catch (\Throwable $e) {
                         Log::warning("Failed to calculate digest audio duration for run {$this->digestRun->id}: " . $e->getMessage());
                     }

                     $this->digestRun->update([
                         'audio_path' => $finalPath,
                         'audio_status' => 'completed',
                         'audio_duration' => $duration,
                         'completed_at' => $this->digestRun->completed_at ?? now()
                     ]);
                } else {
                     // Refund if no audio generated at all
                     $quotaManager->decrementUsage($user, 'youtube', $cost);
                     $this->digestRun->update(['audio_status' => 'failed']);
                }

            } catch (\Exception $e) {
                // Refund on failure
                $quotaManager->decrementUsage($user, 'youtube', $cost);
                throw $e;
            } finally {
                // Cleanup temp files
                foreach ($tempFiles as $file) {
                    if (file_exists($file)) unlink($file);
                }
            }
        } catch (\Exception $e) {
            Log::error("Audio Digest Generation Failed: " . $e->getMessage());
            $this->digestRun->update(['audio_status' => 'failed']);
            throw $e;
        }
    }

    private function detectLanguage($text)
    {
        // Simple heuristic: count common stopwords
        $languages = [
            'en' => ['the', 'and', 'is', 'in', 'to', 'of', 'it', 'you', 'that', 'he', 'was', 'for', 'on', 'are', 'with', 'as', 'I', 'his', 'they', 'be'],
            'es' => ['el', 'la', 'de', 'que', 'y', 'en', 'un', 'una', 'es', 'por', 'para', 'con', 'los', 'las', 'su', 'al', 'lo', 'como', 'más', 'o'],
            'fr' => ['le', 'la', 'de', 'et', 'un', 'une', 'est', 'pour', 'dans', 'les', 'au', 'il', 'que', 'en', 'des', 'du', 'par', 'sur', 'se', 'pas'],
            'it' => ['il', 'la', 'di', 'e', 'un', 'una', 'è', 'per', 'in', 'i', 'le', 'che', 'con', 'del', 'non', 'si', 'al', 'ma', 'della', 'da'],
            'pt' => ['o', 'a', 'de', 'e', 'um', 'uma', 'é', 'para', 'em', 'os', 'as', 'que', 'com', 'do', 'não', 'se', 'na', 'por', 'mais', 'da'],
            // Japanese is distinct by character set, but let's include simple particle check if likely raw text
            'ja' => ['の', 'に', 'は', 'を', 'た', 'が', 'で', 'て', 'と', 'し', 'れ', 'さ', 'あ', 'い', 'う', 'え', 'お'],
        ];

        $text = mb_strtolower($text);
        $bestLang = 'en'; // Default
        $maxCount = 0;

        foreach ($languages as $lang => $words) {
            $count = 0;
            if ($lang === 'ja') {
                // Character matching for JA
                foreach ($words as $char) {
                     $count += mb_substr_count($text, $char);
                }
            } else {
                // Word matching for others
                foreach ($words as $word) {
                    $count += substr_count($text, ' ' . $word . ' ');
                }
            }

            if ($count > $maxCount) {
                $maxCount = $count;
                $bestLang = $lang;
            }
        }

        return $bestLang;
    }
}
