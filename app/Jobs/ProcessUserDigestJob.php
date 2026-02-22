<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Video;
use App\Models\Channel;
use App\Services\ApifyService;
use App\Services\OpenAIService;
use App\Services\GeminiService;
use App\Services\QuotaManager;
use App\Mail\DailyDigest;
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

class ProcessUserDigestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $options;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, array $options = [])
    {
        $this->user = $user;
        $this->options = $options;
    }

    /**
     * Execute the job.
     */
    public function handle(ApifyService $apify, OpenAIService $openAI, GeminiService $gemini, QuotaManager $quotaManager): void
    {
        $user = $this->user;
        Log::info("Processing queued digest for user: {$user->email}");

        $user->load(['channels' => function($q) {
            $q->where('is_paused', false);
        }]);

        if ($user->channels->isEmpty()) {
            Log::info("User {$user->email} has no active channels. Skipping.");
            return;
        }

        $channelUrls = $user->channels->pluck('url')->toArray();
        $remaining = $quotaManager->getRemainingQuota($user, 'youtube');
        
        $limit = $this->options['limit'] ?? null;
        $sort = $this->options['sort'] ?? null;
        $daysBack = $this->options['days_back'] ?? 1;

        if ($limit) {
            $maxVideosPerChannel = (int)ceil($limit / count($channelUrls));
        } else {
            $maxVideosPerChannel = min(100, (int)floor($remaining / count($channelUrls)));
        }

        if ($maxVideosPerChannel * count($channelUrls) > $remaining) {
            $maxAuthored = (int)floor($remaining / count($channelUrls));
            if ($maxVideosPerChannel > $maxAuthored) {
                $maxVideosPerChannel = $maxAuthored;
            }
        }
        
        if ($maxVideosPerChannel <= 0) {
            Log::info("User {$user->email} has insufficient credits. Skipping.");
            return;
        }

        $estimatedCost = $maxVideosPerChannel * count($channelUrls);
        $quotaManager->incrementUsage($user, 'youtube', $estimatedCost, 'digest_freeze');

        $input = [
            'channelUrls' => $channelUrls,
            'dateFilterMode' => 'relative',
            'daysBack' => (int)$daysBack,
            'downloadSubtitles' => true,
            'enableSummary' => false,
            'includeTimestamps' => true,
            'maxShortsPerChannel' => 0,
            'maxStreamsPerChannel' => 0,
            'maxVideosPerChannel' => $maxVideosPerChannel,
            'preferAutoSubtitles' => false,
        ];
        
        if ($sort) {
            $input['sortBy'] = $sort;
        }

        $actorId = 'https://leandrocb88--youtube-video-transcript-actor.apify.actor'; 
        
        try {
            $items = $apify->runActorSyncGetDatasetItems($actorId, $input);
        } catch (\Exception $e) {
            Log::error("Apify Failed for user {$user->id}: " . $e->getMessage());
            $quotaManager->decrementUsage($user, 'youtube', $estimatedCost);
            return;
        }

        if (empty($items)) {
            Log::info("No new videos found for user {$user->email}.");
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
                'digest_date' => $batchTimestamp,
            ]);

            $video->title = $item['title'] ?? 'Unknown Title';
            $video->thumbnail_url = $item['thumbnailUrl'] ?? $item['thumbnail'] ?? "https://img.youtube.com/vi/{$videoId}/mqdefault.jpg";
            $video->duration = $item['duration'] ?? $item['lengthSeconds'] ?? null;
            
            if (is_string($video->duration) && str_contains($video->duration, ':')) {
                $parts = array_reverse(explode(':', $video->duration));
                $seconds = 0;
                foreach ($parts as $index => $part) $seconds += (int)$part * pow(60, $index);
                $video->duration = $seconds;
            }

            $video->transcript = $this->parseTranscript($item);

            if (!$video->duration && !empty($video->transcript)) {
                $lastSegment = end($video->transcript);
                if ($lastSegment) {
                    $video->duration = (int) ceil(($lastSegment['start'] ?? 0) + ($lastSegment['duration'] ?? 0));
                }
            }
            
            $video->source = 'digest';
            $video->share_token = $shareToken;
            
            $channelUrl = $item['channelUrl'] ?? '';
            $apifyChannelId = $item['channelId'] ?? null;
            $channelName = $item['channel'] ?? $item['channelName'] ?? $item['author'] ?? 'Unknown Channel'; 
            
            $video->channel_title = $channelName;
            
            if (!$apifyChannelId && preg_match('/channel\/(UC[\w-]+)/', $channelUrl, $connMatches)) {
                $apifyChannelId = $connMatches[1];
            }

            $channel = $user->channels->first(function($c) use ($channelUrl, $apifyChannelId, $channelName) {
                if ($apifyChannelId && $c->youtube_channel_id === $apifyChannelId) return true;
                if ($channelUrl && (str_contains($channelUrl, $c->url) || $channelUrl == $c->url)) return true;
                if ($channelName && (stripos($c->name, $channelName) !== false || stripos($channelName, $c->name) !== false)) return true;
                return false;
            });

            if ($channel) {
                $video->channel_id = $channel->id;
            }

            $video->save();
            $videosToSend[] = $video;

            if (empty($video->summary_detailed)) {
                $fullText = collect($video->transcript)->pluck('text')->join(' ');
                if (!empty($fullText)) {
                     $video->update(['summary_status' => 'processing']);
                     $videosToSummarize[] = ['video' => $video, 'text' => $fullText];
                }
            }
        }

        if (!empty($videosToSummarize)) {
            $chunks = array_chunk($videosToSummarize, 10);
            $provider = config('services.ai.provider', 'openai');

            foreach ($chunks as $chunk) {
                 $responses = Http::pool(function (Pool $pool) use ($chunk, $provider, $openAI, $gemini) {
                     foreach ($chunk as $index => $job) {
                         if ($provider === 'gemini') {
                             $gemini->addToPool($pool, (string)$index, $job['text'], 'detailed');
                         } else {
                             $openAI->addToPool($pool, (string)$index, $job['text'], 'detailed');
                         }
                     }
                 });

                 foreach ($responses as $index => $response) {
                     if ($response->ok()) {
                         $summary = null;
                         if ($provider === 'gemini') $summary = $gemini->parseResponse($response);
                         else $summary = $openAI->parseResponse($response);
                         
                         if ($summary) {
                             $video = $chunk[$index]['video'];
                             $video->update([
                                'summary_detailed' => $summary,
                                'summary_status' => 'completed'
                             ]);
                         }
                     } else {
                         Log::error("Digest Summary Failed: " . $response->body());
                         if (isset($chunk[$index])) {
                            $chunk[$index]['video']->update(['summary_status' => 'failed']);
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
                'published_at' => $video->created_at ? $video->created_at->format('M d, Y') : null,
             ];
        }

        $actualCount = count($processedVideos);
        $diff = max(0, $estimatedCost - $actualCount);
        if ($diff > 0) {
            $quotaManager->decrementUsage($user, 'youtube', $diff);
        }

        if (!empty($processedVideos)) {
            $run = \App\Models\DigestRun::create([
                'user_id' => $user->id,
                'digest_id' => null,
                'batch_id' => $shareToken,
                'summary_count' => count($processedVideos),
                'total_duration' => collect($videosToSend)->sum('duration'),
            ]);

            $totalVideos = count($processedVideos);
            $totalDurationSeconds = collect($videosToSend)->sum('duration');
            $totalWords = collect($processedVideos)->sum(fn($v) => str_word_count(strip_tags($v['summary'] ?? '')));
            $readTimeSeconds = ceil(($totalWords / 200) * 60);
            $timeSavedSeconds = max(0, $totalDurationSeconds - $readTimeSeconds);

            $summaryMetrics = [
                'total_videos' => $totalVideos,
                'total_duration' => $this->formatDuration($totalDurationSeconds),
                'read_time' => $this->formatDuration($readTimeSeconds),
                'time_saved' => $this->formatDuration($timeSavedSeconds),
            ];

            Mail::to($user->email)->send(new DailyDigest($user, $processedVideos, now()->format('F j, Y'), $shareToken, $summaryMetrics));
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
