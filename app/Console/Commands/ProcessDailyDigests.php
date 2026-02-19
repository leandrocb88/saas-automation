<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Video;
use App\Models\Channel;
use App\Services\ApifyService;
use App\Services\OpenAIService;
use App\Services\GeminiService;
use App\Services\QuotaManager;
use App\Mail\DailyDigest;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use Carbon\Carbon;

class ProcessDailyDigests extends Command
{
    protected $signature = 'app:process-daily-digests 
                            {--force : Run immediately without checking schedule} 
                            {--user= : Process only for specific user ID}
                            {--limit= : Total max videos to fetch across all channels}
                            {--sort= : Sort order (newest, oldest, relevance)}
                            {--days-back= : Number of days to look back}';
    protected $description = 'Process and send daily email digests for subscribed channels.';

    public function handle(ApifyService $apify, OpenAIService $openAI, GeminiService $gemini, QuotaManager $quotaManager)
    {
        $this->info('Starting Daily Digest Process...');

        $currentHour = Carbon::now()->format('H'); // 00-23
        $force = $this->option('force');
        $userId = $this->option('user');
        
        $query = User::query();

        if ($userId) {
            $query->where('id', $userId);
        } else {
             $query->whereHas('digestSchedule', function($q) use ($currentHour, $force) {
                $q->where('is_active', true);
                
                if (!$force) {
                    $q->where('preferred_time', 'like', "{$currentHour}:%");
                }
            });
        }

        $users = $query->with(['channels' => function($q) {
            $q->where('is_paused', false);
        }, 'digestSchedule'])->get();

        if ($users->isEmpty()) {
             $this->info($force ? 'No active users found.' : 'No users scheduled for this hour.');
             return;
        }

        foreach ($users as $user) {
            $this->info("Processing digest for user: {$user->email}");

            if ($user->channels->isEmpty()) {
                $this->info("User has no channels. Skipping.");
                continue;
            }

            // 2. Prepare payload for Apify
            $channelUrls = $user->channels->pluck('url')->toArray();
            
            if (empty($channelUrls)) continue;

        // Check available credits and limit fetch accordingly
            $remaining = $quotaManager->getRemainingQuota($user, 'youtube');
            
            $limit = $this->option('limit');
            $sort = $this->option('sort');
            $daysBack = $this->option('days-back') ?? 1;

            if ($limit) {
                // User specified total limit
                $maxVideosPerChannel = (int)ceil($limit / count($channelUrls));
            } else {
                $maxVideosPerChannel = min(100, (int)floor($remaining / count($channelUrls)));
            }

            // Ensure we don't exceed quota even with manual limit (optional safeguard, or let it fail/partial?)
            // Let's safe guard:
            if ($maxVideosPerChannel * count($channelUrls) > $remaining) {
                // Adjust to fit quota? Or just error? 
                // Let's adjust maxVideosPerChannel to fit quota if force is not used? 
                // Actually, if user explicitly asks for 50, and has 20 credits, maybe we should warn?
                // For now, let's respect quota as hard limit.
                 $maxAuthored = (int)floor($remaining / count($channelUrls));
                 if ($maxVideosPerChannel > $maxAuthored) {
                     $maxVideosPerChannel = $maxAuthored;
                     $this->warn("User quota limits fetch to {$maxVideosPerChannel} per channel.");
                 }
            }
            
            if ($maxVideosPerChannel <= 0) {
                $this->info("User has insufficient credits. Skipping.");
                continue;
            }

            // Estimate cost (rough, will be adjusted after actual fetch)
            $estimatedCost = $maxVideosPerChannel * count($channelUrls);
            
            // Freeze estimated credits
            $quotaManager->incrementUsage($user, 'youtube', $estimatedCost, 'digest_freeze');

            // Updated Payload per user request
            $input = [
                'channelUrls' => $channelUrls,
                'dateFilterMode' => 'relative',
                'daysBack' => (int)$daysBack,
                'downloadSubtitles' => true,
                'enableSummary' => false, // We do it locally
                'includeTimestamps' => true,
                'maxShortsPerChannel' => 0,
                'maxStreamsPerChannel' => 0,
                'maxVideosPerChannel' => $maxVideosPerChannel,
                'preferAutoSubtitles' => false,
            ];
            
            if ($sort) {
                $input['sortBy'] = $sort;
            }

            $this->info("Fetching videos from Apify...");
            // Use existing actor ID
        // $actorId = 'leandrocb88~youtube-video-transcript-actor';
        // Use Standby Mode URL
        $actorId = 'https://leandrocb88--youtube-video-transcript-actor.apify.actor'; 
            
            try {
                $items = $apify->runActorSyncGetDatasetItems($actorId, $input);
            } catch (\Exception $e) {
                Log::error("Apify Failed for user {$user->id}: " . $e->getMessage());
                // Refund on failure
                $quotaManager->decrementUsage($user, 'youtube', $estimatedCost);
                continue;
            }

            if (empty($items)) {
                $this->info("No new videos found.");
                // Refund if no items
                $quotaManager->decrementUsage($user, 'youtube', $estimatedCost);
                continue;
            }

            $digestDate = Carbon::yesterday()->format('Y-m-d'); // Grouping by "Yesterday's content" or "Today's Digest"
            $batchTimestamp = now();
            $shareToken = \Illuminate\Support\Str::uuid()->toString(); // Unique token for this batch
            
            $videosToSend = [];
            $videosToSummarize = []; // ['id' => pid, 'video' => model, 'text' => transcript]
            $processedVideos = [];

            foreach ($items as $item) {
                // Parse Data
                $videoId = $this->extractVideoId($item['url'] ?? $item['videoUrl'] ?? '');
                if (!$videoId) continue;
                
                $video = Video::firstOrNew([
                    'user_id' => $user->id,
                    'video_id' => $videoId,
                    'digest_date' => $batchTimestamp, // Scope to this specific batch run
                ]);

                // Update metadata
                $video->title = $item['title'] ?? 'Unknown Title';
                $video->thumbnail_url = $item['thumbnailUrl'] ?? $item['thumbnail'] ?? "https://img.youtube.com/vi/{$videoId}/mqdefault.jpg";
                $video->duration = $item['duration'] ?? $item['lengthSeconds'] ?? null;
                
                // Handle "MM:SS" or "HH:MM:SS" strings for Duration
                if (is_string($video->duration) && str_contains($video->duration, ':')) {
                    $parts = array_reverse(explode(':', $video->duration));
                    $seconds = 0;
                    foreach ($parts as $index => $part) {
                        $seconds += (int)$part * pow(60, $index);
                    }
                    $video->duration = $seconds;
                }

                $video->transcript = $this->parseTranscript($item);

                // If duration is missing, try to infer from transcript
                if (!$video->duration && !empty($video->transcript)) {
                    $transcript = $video->transcript;
                    $lastSegment = end($transcript);
                    if ($lastSegment) {
                        $video->duration = (int) ceil(($lastSegment['start'] ?? 0) + ($lastSegment['duration'] ?? 0));
                    }
                }
                
                // Tagging
                $video->source = 'digest';
                $video->share_token = $shareToken; // Assign batch token
                // digest_date is already set in firstOrNew
                
                // Assign Channel ID
                $channelUrl = $item['channelUrl'] ?? '';
                $apifyChannelId = $item['channelId'] ?? null;
                $channelName = $item['channel'] ?? $item['channelName'] ?? $item['author'] ?? 'Unknown Channel'; 
                
                $video->channel_title = $channelName;
                
                // Extract Channel ID from Apify URL (e.g. https://www.youtube.com/channel/UC...)
                if (!$apifyChannelId && preg_match('/channel\/(UC[\w-]+)/', $channelUrl, $connMatches)) {
                    $apifyChannelId = $connMatches[1];
                }

                $channel = $user->channels->first(function($c) use ($channelUrl, $apifyChannelId, $channelName) {
                    // 1. Precise Match by ID
                    if ($apifyChannelId && $c->youtube_channel_id === $apifyChannelId) {
                        return true;
                    }
                    // 2. URL Match
                    if ($channelUrl && (str_contains($channelUrl, $c->url) || $channelUrl == $c->url)) {
                        return true;
                    }
                    // 3. Name Match (case-insensitive, flexible)
                    if ($channelName && stripos($c->name, $channelName) !== false) {
                        return true;
                    }
                    // 4. Reverse: Check if subscribed channel name is in Apify's channel name
                    if ($channelName && stripos($channelName, $c->name) !== false) {
                        return true;
                    }
                    return false;
                });

                if ($channel) {
                    $video->channel_id = $channel->id;
                    Log::info("Matched channel: {$channel->name} using name match");
                } else {
                    Log::warning("Could not match channel for video {$videoId}. ChannelUrl: {$channelUrl}, ChannelId: {$apifyChannelId}, ChannelName: {$channelName}");
                }

                $video->save();
                
                $videosToSend[] = $video;

                // Generating Summary Queue
                if (empty($video->summary_detailed)) {
                    $fullText = collect($video->transcript)->pluck('text')->join(' ');
                    if (!empty($fullText)) {
                         $video->update(['summary_status' => 'processing']);
                         $videosToSummarize[] = ['video' => $video, 'text' => $fullText];
                    }
                }
            }

            // Process Summaries in Parallel
            if (!empty($videosToSummarize)) {
                $this->info("Generating summaries for " . count($videosToSummarize) . " videos in parallel...");
                set_time_limit(0); 

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
                             if ($provider === 'gemini') {
                                 $summary = $gemini->parseResponse($response);
                             } else {
                                 $summary = $openAI->parseResponse($response);
                             }
                             
                             if ($summary) {
                                 $video = $chunk[$index]['video'];
                                 $video->update([
                                    'summary_detailed' => $summary,
                                    'summary_status' => 'completed'
                                 ]);
                             }
                         } else {
                             Log::error("Digest Summary Failed: " . $response->body());
                             // Check if we can fail the specific video?
                             // The response index matches the chunk index.
                             if (isset($chunk[$index])) {
                                $chunk[$index]['video']->update(['summary_status' => 'failed']);
                             }
                         }
                     }
                     
                     sleep(1);
                }
            }
            
            // Format for Email
            foreach ($videosToSend as $video) {
                 $processedVideos[] = [
                     'title' => $video->title,
                    'videoUrl' => "https://www.youtube.com/watch?v={$video->video_id}",
                    'thumbnail' => $video->thumbnail_url, // Ensure this exists or fallback
                    'summary' => $video->summary_detailed,
                    'appUrl' => route('youtube.show', $video),
                    'channel_name' => $video->channel ? $video->channel->name : $video->channel_title,
                    'channel_thumbnail' => $video->channel ? $video->channel->thumbnail_url : null,
                    'channel_url' => $video->channel ? $video->channel->url : null,
                    'published_at' => $video->created_at ? $video->created_at->format('M d, Y') : null,
                 ];
            }

            // 3. Refund unused credits
            $actualCount = count($processedVideos);
            $diff = max(0, $estimatedCost - $actualCount);
            if ($diff > 0) {
                $quotaManager->decrementUsage($user, 'youtube', $diff);
                $this->info("Refunded {$diff} unused credits.");
            }

            // 4. Send Email & Create Run
            if (!empty($processedVideos)) {
                $this->info("Sending email with " . count($processedVideos) . " videos.");
                
                // Create Digest Run
                $run = \App\Models\DigestRun::create([
                    'user_id' => $user->id,
                    'digest_id' => null, // Daily digests are not linked to a specific custom Digest config yet
                    'batch_id' => $shareToken,
                    'summary_count' => count($processedVideos),
                    'total_duration' => collect($videosToSend)->sum('duration'),
                ]);

                // Generation is now on-demand (user click)
                // \App\Jobs\GenerateDigestPdf::dispatch($run);
                // \App\Jobs\GenerateDigestAudio::dispatch($run);

                // Calculate Metrics
                $totalVideos = count($processedVideos);
                $totalDurationSeconds = collect($videosToSend)->sum('duration');
                
                // Estimate Read Time (approx 200 words per minute)
                $totalWords = collect($processedVideos)->sum(fn($v) => str_word_count(strip_tags($v['summary'] ?? '')));
                $readTimeSeconds = ceil(($totalWords / 200) * 60); // 200 wpm
                
                // Time Saved
                $timeSavedSeconds = max(0, $totalDurationSeconds - $readTimeSeconds);

                // Format times for display
                $summaryMetrics = [
                    'total_videos' => $totalVideos,
                    'total_duration' => $this->formatDuration($totalDurationSeconds),
                    'read_time' => $this->formatDuration($readTimeSeconds),
                    'time_saved' => $this->formatDuration($timeSavedSeconds),
                ];

                Mail::to($user->email)->send(new DailyDigest($user, $processedVideos, now()->format('F j, Y'), $shareToken, $summaryMetrics));
            }
        }

        $this->info('Daily Digest Process Completed.');
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
        // Same parsing logic as Controller
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
        if ($seconds < 60) {
            return "{$seconds}s";
        }
        
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }
        
        return "{$minutes}m";
    }
}
