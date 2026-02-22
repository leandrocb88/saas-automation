<?php

namespace App\Jobs;

use App\Models\Digest;
use App\Models\Video;
use App\Services\ApifyService;
use App\Services\OpenAIService;
use App\Services\GeminiService;
use App\Services\QuotaManager;
use App\Mail\CustomDigestMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use Carbon\Carbon;

class ProcessCustomDigestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $digest;

    /**
     * Create a new job instance.
     */
    public function __construct(Digest $digest)
    {
        $this->digest = $digest;
    }

    /**
     * Execute the job.
     */
    public function handle(ApifyService $apify, OpenAIService $openAI, GeminiService $gemini, QuotaManager $quotaManager): void
    {
        $digest = $this->digest;
        $user = $digest->user;
        Log::info("Processing queued custom digest '{$digest->name}' for user: {$user->email}");

        $sourceUrls = [];
        if ($digest->mode === 'channels' || $digest->mode === 'mixed') {
            foreach ($digest->channels as $channel) {
                $sourceUrls[] = $channel->url;
            }
        }
        
        if (empty($sourceUrls)) {
            Log::warn("Digest '{$digest->name}' has no active sources. Skipping.");
            return;
        }

        $remaining = $quotaManager->getRemainingQuota($user, 'youtube');
        $maxVideosPerSource = min(50, (int)floor($remaining / count($sourceUrls)));

        if ($maxVideosPerSource <= 0) {
            Log::warn("User {$user->email} has insufficient credits for custom digest. Skipping.");
            return;
        }

        $estimatedCost = $maxVideosPerSource * count($sourceUrls);
        $quotaManager->incrementUsage($user, 'youtube', $estimatedCost, 'custom_digest_freeze');

        $input = [
            'channelUrls' => $sourceUrls,
            'dateFilterMode' => 'relative',
            'daysBack' => 1,
            'downloadSubtitles' => true,
            'enableSummary' => false,
            'includeTimestamps' => true,
            'maxShortsPerChannel' => 0,
            'maxStreamsPerChannel' => 0,
            'maxVideosPerChannel' => $maxVideosPerSource,
            'preferAutoSubtitles' => false,
        ];

        $actorId = 'https://leandrocb88--youtube-video-transcript-actor.apify.actor';

        try {
            $items = $apify->runActorSyncGetDatasetItems($actorId, $input);
        } catch (\Exception $e) {
            Log::error("Apify Failed for digest {$digest->id}: " . $e->getMessage());
            $quotaManager->decrementUsage($user, 'youtube', $estimatedCost);
            return;
        }

        if (empty($items)) {
            Log::info("No new videos found for custom digest {$digest->id}.");
            $quotaManager->decrementUsage($user, 'youtube', $estimatedCost);
            return;
        }

        $batchTimestamp = now();
        $shareToken = \Illuminate\Support\Str::uuid()->toString();
        
        $videosToSend = [];
        $videosToSummarize = [];
        $processedVideos = [];

        foreach ($items as $item) {
            $videoId = $this->extractVideoId($item['url'] ?? $item['videoUrl'] ?? '');
            if (!$videoId) continue;
            
            $video = Video::firstOrNew([
                'user_id' => $user->id,
                'video_id' => $videoId,
            ]);

            $video->digest_date = $batchTimestamp; 
            $video->source = 'custom_digest';
            $video->share_token = $shareToken;
            $video->title = $item['title'] ?? 'Unknown';
            $video->thumbnail_url = $item['thumbnailUrl'] ?? $item['thumbnail'] ?? "https://img.youtube.com/vi/{$videoId}/mqdefault.jpg";
            
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
            
            if (!$video->duration && !empty($video->transcript)) {
                $last = end($video->transcript);
                $video->duration = (int) ceil(($last['start']??0) + ($last['duration']??0));
            }

            $channelUrl = $item['channelUrl'] ?? '';
            $channelName = $item['channel'] ?? $item['channelName'] ?? 'Unknown';
            $video->channel_title = $channelName;
            
            $matchedChannel = $digest->channels->first(function($c) use ($channelUrl, $channelName) {
                return (str_contains($channelUrl, $c->url)) || (stripos($c->name, $channelName) !== false);
            });
            
            if ($matchedChannel) {
                $video->channel_id = $matchedChannel->id;
            }

            $video->save();
            $videosToSend[] = $video;
            
            $shouldSummarize = !empty($digest->custom_prompt) || empty($video->summary_detailed);
            
            if ($shouldSummarize) {
                 $fullText = collect($video->transcript)->pluck('text')->join(' ');
                  if (!empty($fullText)) {
                       $video->update(['summary_status' => 'processing']);
                       $videosToSummarize[] = [
                           'video' => $video,
                           'text' => $fullText,
                           'prompt' => $digest->custom_prompt
                       ];
                  }
            }
        }

        if (!empty($videosToSummarize)) {
            $chunks = array_chunk($videosToSummarize, 5);
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
        }

        if (!empty($processedVideos)) {
            $run = \App\Models\DigestRun::create([
                'digest_id' => $digest->id,
                'user_id' => $user->id,
                'batch_id' => $shareToken,
                'summary_count' => count($processedVideos),
                'total_duration' => collect($videosToSend)->sum('duration'),
            ]);

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
