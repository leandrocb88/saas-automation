<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Digest;
use App\Models\Video;
use App\Services\ApifyService;
use App\Services\OpenAIService;
use App\Services\GeminiService;
use App\Services\QuotaManager;
use App\Mail\CustomDigestMail; // We'll need to create this
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use Carbon\Carbon;

class ProcessCustomDigests extends Command
{
    protected $signature = 'app:process-custom-digests {--force : Run immediately for all active digests} {--digest= : Process specific digest ID}';
    protected $description = 'Process and send custom email digests.';

    public function handle(ApifyService $apify, OpenAIService $openAI, GeminiService $gemini, QuotaManager $quotaManager)
    {
        $this->info('Starting Custom Digest Process...');

        $now = Carbon::now();
        $currentTime = $now->format('H:i');
        $currentDay = strtolower($now->format('D')); // mon, tue, etc.
        
        $force = $this->option('force');
        $digestId = $this->option('digest');

        $query = Digest::query()->where('is_active', true);

        if ($digestId) {
            $query->where('id', $digestId);
        } elseif (!$force) {
            $query->where('scheduled_at', $currentTime);
            $query->where(function($q) use ($currentDay) {
                $q->where('frequency', 'daily')
                  ->orWhere(function($sub) use ($currentDay) {
                      $sub->where('frequency', 'weekly')
                          ->where('day_of_week', $currentDay);
                  });
            });
        }

        $digests = $query->with(['user', 'channels'])->get();

        if ($digests->isEmpty()) {
            $this->info('No digests due for processing.');
            return;
        }

        foreach ($digests as $digest) {
            $this->processDigest($digest, $apify, $openAI, $gemini, $quotaManager);
        }

        $this->info('Custom Digest Process Completed.');
    }

    private function processDigest(Digest $digest, ApifyService $apify, OpenAIService $openAI, GeminiService $gemini, QuotaManager $quotaManager)
    {
        $user = $digest->user;
        $this->info("Processing digest '{$digest->name}' for user: {$user->email}");

        // 1. Determine Sources
        $sourceUrls = [];
        
        if ($digest->mode === 'channels' || $digest->mode === 'mixed') {
            foreach ($digest->channels as $channel) {
                $sourceUrls[] = $channel->url;
            }
        }
        
        // TODO: Handle 'search_term' mode if we implement a search actor. For now, only channels.
        
        if (empty($sourceUrls)) {
            $this->warn("Digest '{$digest->name}' has no active sources. Skipping.");
            return;
        }

        // 2. Check & Freeze Quota
        $remaining = $quotaManager->getRemainingQuota($user, 'youtube');
        // Limit videos per source to avoid draining credits too fast on a custom run
        // Let's say 5 videos per channel max for custom digests, or calculate based on budget.
        // ProcessDailyDigests uses logic: min(100, remaining / count).
        $maxVideosPerSource = min(50, (int)floor($remaining / count($sourceUrls)));

        if ($maxVideosPerSource <= 0) {
            $this->warn("User has insufficient credits. Skipping.");
            return;
        }

        $estimatedCost = $maxVideosPerSource * count($sourceUrls);
        $quotaManager->incrementUsage($user, 'youtube', $estimatedCost, 'custom_digest_freeze');

        // 3. Prepare Apify Input
        $input = [
            'channelUrls' => $sourceUrls,
            'dateFilterMode' => 'relative',
            'daysBack' => 1, // Default to yesterday/today unless we add a setting to Digest model for time range
            'downloadSubtitles' => true,
            'enableSummary' => false, // We do it locally with custom prompt
            'includeTimestamps' => true,
            'maxShortsPerChannel' => 0,
            'maxStreamsPerChannel' => 0,
            'maxVideosPerChannel' => $maxVideosPerSource,
            'preferAutoSubtitles' => false,
        ];

        $this->info("Fetching videos from Apify...");
        // $actorId = 'leandrocb88~youtube-video-transcript-actor';
        // Use Standby Mode URL
        $actorId = 'https://leandrocb88--youtube-video-transcript-actor.apify.actor';

        try {
            $items = $apify->runActorSyncGetDatasetItems($actorId, $input);
        } catch (\Exception $e) {
            Log::error("Apify Failed for digest {$digest->id}: " . $e->getMessage());
            $quotaManager->decrementUsage($user, 'youtube', $estimatedCost);
            return;
        }

        if (empty($items)) {
            $this->info("No new videos found.");
            $quotaManager->decrementUsage($user, 'youtube', $estimatedCost);
            return;
        }

        // 4. Process Items
        $batchTimestamp = now();
        $shareToken = \Illuminate\Support\Str::uuid()->toString();
        
        $videosToSend = [];
        $videosToSummarize = [];
        $processedVideos = [];

        foreach ($items as $item) {
            $videoId = $this->extractVideoId($item['url'] ?? $item['videoUrl'] ?? '');
            if (!$videoId) continue;

            // We use 'firstOrNew' scoping by user and video. 
            // Note: A video might already exist from 'daily digest' or 'channel analysis'.
            // If we want to store specific 'custom digest' instance data, we might need a pivot or just reuse the video.
            // But 'digest_date' in Video model is used for grouping in the UI. 
            // If we update 'digest_date', it moves to Today.
            // If we want to keep it separate, we might need a new 'DigestRun' model.
            // For MVP: Let's treat it as a fresh "Digest" entry, updating the video to be part of *this* batch.
            // BUT, if we overwrite `digest_date`, it effectively moves it to this batch.
            
            $video = Video::firstOrNew([
                'user_id' => $user->id,
                'video_id' => $videoId,
            ]);

            // If it exists, we might be updating it. 
            // We should ideally *not* overwrite `digest_date` if it was from a different major batch?
            // Actually, the `process-daily-digests` sets `digest_date`.
            // The `DigestController::digest` groups by `digest_date`.
            // If we run a custom digest now, it will appear as a new batch today. That's fine.
            
            $video->digest_date = $batchTimestamp; 
            $video->source = 'custom_digest'; // Mark source
            $video->share_token = $shareToken;
            
            // Update metadata
            $video->title = $item['title'] ?? 'Unknown';
            $video->thumbnail_url = $item['thumbnailUrl'] ?? $item['thumbnail'] ?? "https://img.youtube.com/vi/{$videoId}/mqdefault.jpg";
            
            // Duration parsing
            $durationRaw = $item['duration'] ?? $item['lengthSeconds'] ?? null;
            if (is_string($durationRaw) && str_contains($durationRaw, ':')) {
                $parts = array_reverse(explode(':', $durationRaw));
                $seconds = 0;
                foreach ($parts as $idx => $part) $seconds += (int)$part * pow(60, $idx);
                $video->duration = $seconds;
            } else {
                $video->duration = $durationRaw;
            }

            $video->transcript = $this->parseTranscript($item);
            
            // Infer duration if missing
            if (!$video->duration && !empty($video->transcript)) {
                $last = end($video->transcript);
                $video->duration = (int) ceil(($last['start']??0) + ($last['duration']??0));
            }

            // Match Channel
            $channelUrl = $item['channelUrl'] ?? '';
            $channelName = $item['channel'] ?? $item['channelName'] ?? 'Unknown';
            $video->channel_title = $channelName;
            
            // Try to link to local Channel model
            $matchedChannel = $digest->channels->first(function($c) use ($channelUrl, $channelName) {
                return (str_contains($channelUrl, $c->url)) || (stripos($c->name, $channelName) !== false);
            });
            
            if ($matchedChannel) {
                $video->channel_id = $matchedChannel->id;
            }

            $video->save();
            $videosToSend[] = $video;

            // Prepare for Summarization
            // Always re-summarize if we have a Custom Prompt, OR if summary is missing.
            // If `digest->custom_prompt` is set, we MUST regenerate summary to obey constraints.
            // This might overwrite existing generic summaries. That is intended/desirable for custom digests.
            
            $shouldSummarize = !empty($digest->custom_prompt) || empty($video->summary_detailed);
            
            if ($shouldSummarize) {
                 $fullText = collect($video->transcript)->pluck('text')->join(' ');
                  if (!empty($fullText)) {
                       $video->update(['summary_status' => 'processing']);
                       $videosToSummarize[] = [
                           'video' => $video,
                           'text' => $fullText,
                           'prompt' => $digest->custom_prompt // Pass the specific prompt
                       ];
                  }
            }
        }

        // 5. Run Summaries
        if (!empty($videosToSummarize)) {
            $this->info("Summarizing " . count($videosToSummarize) . " videos (Prompt: " . ($digest->custom_prompt ? 'Custom' : 'Default') . ")...");
            
            $chunks = array_chunk($videosToSummarize, 5); // Smaller chunks for reliability
            $provider = config('services.ai.provider', 'openai');

            foreach ($chunks as $chunk) {
                $responses = Http::pool(function (Pool $pool) use ($chunk, $provider, $openAI, $gemini) {
                    foreach ($chunk as $idx => $job) {
                        if ($provider === 'gemini') {
                            $gemini->addToPool($pool, (string)$idx, $job['text'], 'detailed', $job['prompt']);
                        } else {
                            $openAI->addToPool($pool, (string)$idx, $job['text'], 'detailed', $job['prompt']);
                        }
                    }
                });

                foreach ($responses as $idx => $response) {
                    if ($response->ok()) {
                        $summary = null;
                         if ($provider === 'gemini') $summary = $gemini->parseResponse($response);
                         else $summary = $openAI->parseResponse($response);
                                                  if ($summary) {
                              $video = $chunk[$idx]['video'];
                              $video->update([
                                'summary_detailed' => $summary,
                                'summary_status' => 'completed'
                              ]);
                          }
                     } else {
                         Log::error("Custom Digest Summary Failed: " . $response->body());
                         if (isset($chunk[$idx])) {
                            $chunk[$idx]['video']->update(['summary_status' => 'failed']);
                         }
                     }
                }
                sleep(1);
            }
        }

        // 6. Format & Refund
        foreach ($videosToSend as $video) {
             $processedVideos[] = [
                 'title' => $video->title,
                'videoUrl' => "https://www.youtube.com/watch?v={$video->video_id}",
                'thumbnail' => $video->thumbnail_url,
                'summary' => $video->summary_detailed,
                'appUrl' => route('youtube.show', $video),
                'channel_name' => $video->channel ? $video->channel->name : $video->channel_title,
                'channel_thumbnail' => $video->channel ? $video->channel->thumbnail_url : null,
                'channel_url' => $video->channel ? $video->channel->url : null,
                'published_at' => $video->created_at->format('M d, Y'),
             ];
        }

        $actualCount = count($processedVideos);
        $diff = max(0, $estimatedCost - $actualCount);
        if ($diff > 0) {
            $quotaManager->decrementUsage($user, 'youtube', $diff);
            $this->info("Refunded {$diff} credits.");
        }

        // 7. Creating Digest Run & Dispatching Assets
        if (!empty($processedVideos)) {
            $run = \App\Models\DigestRun::create([
                'digest_id' => $digest->id,
                'user_id' => $user->id,
                'batch_id' => $shareToken,
                'summary_count' => count($processedVideos),
                'total_duration' => collect($videosToSend)->sum('duration'),
            ]);

            // Generation is now on-demand (user click)
            // \App\Jobs\GenerateDigestPdf::dispatch($run);
            // \App\Jobs\GenerateDigestAudio::dispatch($run);

            $this->info("Sending custom digest email...");
            
            // Calculate Metrics
            $totalDuration = collect($videosToSend)->sum('duration');
            $totalWords = collect($processedVideos)->sum(fn($v) => str_word_count(strip_tags($v['summary'] ?? '')));
            $readTime = ceil(($totalWords / 200) * 60);
            $timeSaved = max(0, $totalDuration - $readTime);

            $metrics = [
                'total_videos' => count($processedVideos),
                'total_duration' => $this->formatDuration($totalDuration),
                'read_time' => $this->formatDuration($readTime),
                'time_saved' => $this->formatDuration($timeSaved),
            ];

            // Reusing DailyDigest mail for now, or create CustomDigestMail if we need meaningful title diff?
            // DailyDigest takes $user, $videos, $date, $token, $metrics.
            // We might want to pass Digest Name? "Your 'Morning Tech' Digest".
            // DailyDigest Mailable currently hardcodes the subject "Your Daily YouTube Digest".
            // We should use a new Mailable: CustomDigestMail.
            
             Mail::to($user->email)->send(new \App\Mail\CustomDigestMail($user, $digest, $processedVideos, now()->format('F j, Y'), $shareToken, $metrics));
        }
    }

    private function extractVideoId($url)
    {
        preg_match('/(?:v=|\/)([\w-]{11})(?:\?|&|$)/', $url, $matches);
        return $matches[1] ?? null;
    }

    private function parseTranscript($item)
    {
        $transcript = [];
        $raw = $item['transcript'] ?? $item['subtitles'] ?? [];
        if (is_array($raw)) {
             $lastStart = -1;
             $currentIndex = -1;
             foreach ($raw as $sub) {
                  $text = $sub['text'] ?? '';
                  $start = (float)($sub['start']??0);
                  $duration = (float)($sub['duration']??0);
                  if ($currentIndex >= 0 && abs($start - $lastStart) < 0.1) {
                       $transcript[$currentIndex]['text'] .= ' ' . $text;
                       $transcript[$currentIndex]['duration'] += $duration;
                  } else {
                       $transcript[] = ['text'=>$text, 'start'=>$start, 'duration'=>$duration];
                       $currentIndex++;
                       $lastStart = $start;
                  }
             }
        }
        return $transcript;
    }

    private function formatDuration($seconds)
    {
        if ($seconds < 60) return "{$seconds}s";
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        if ($hours > 0) return "{$hours}h {$minutes}m";
        return "{$minutes}m";
    }
}
