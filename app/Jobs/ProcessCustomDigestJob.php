<?php

namespace App\Jobs;

use App\Models\Digest;
use App\Models\Video;
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

    public $timeout = 600;

    protected $digest;
    protected $options;

    /**
     * Create a new job instance.
     */
    public function __construct(Digest $digest, array $options = [])
    {
        $this->digest = $digest;
        $this->options = $options;
    }

    /**
     * Execute the job.
     */
    public function handle(\App\Services\RailwayService $railway, OpenAIService $openAI, GeminiService $gemini, QuotaManager $quotaManager): void
    {
        $digest = $this->digest;
        $user = $digest->user;
        $options = $this->options;
        
        Log::info(">>> Starting Digest Process: '{$digest->name}' (ID: {$digest->id}) for {$user->email}");

        $sourceUrls = [];
        if ($digest->mode === 'channels' || $digest->mode === 'mixed') {
            foreach ($digest->channels as $channel) {
                if ($channel->is_paused) {
                    Log::info("Skipping paused channel '{$channel->name}' for digest '{$digest->name}'.");
                    continue;
                }
                $sourceUrls[] = $channel->url;
            }
        }
        
        if (empty($sourceUrls)) {
            Log::warning("Digest '{$digest->name}' has no active sources. Skipping.");
            return;
        }

        $remaining = $quotaManager->getRemainingQuota($user, 'youtube');
        
        $includeSummary = $options['include_summary'] ?? filter_var($digest->settings['include_summary'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $transcriptCost = $quotaManager->getCost($user, 'youtube', 'transcript');
        $summaryCost = $includeSummary ? $quotaManager->getCost($user, 'youtube', 'ai_summary') : 0;
        $costPerVideo = $transcriptCost + $summaryCost;

        if ($costPerVideo > 0) {
            $maxVideosPerSource = min(50, (int)floor($remaining / ($costPerVideo * count($sourceUrls))));
        } else {
            $maxVideosPerSource = 50;
        }

        if ($maxVideosPerSource <= 0 && !($options['bypass_quota'] ?? false)) {
            Log::warning("User {$user->email} has insufficient credits for custom digest. Skipping.");
            return;
        }

        if ($options['bypass_quota'] ?? false) {
            Log::info("QUOTA BYPASSED for testing.");
            $maxVideosPerSource = $options['limit'] ?? 10;
        }

        $estimatedCost = ($options['bypass_quota'] ?? false) ? 0 : $maxVideosPerSource * count($sourceUrls) * $costPerVideo;
        if ($estimatedCost > 0) {
            $quotaManager->incrementUsage($user, 'youtube', $estimatedCost, 'custom_digest_freeze');
        }

        $processedCount = 0;

        try {
            $daysBack = $options['days_back'] ?? ($digest->frequency === 'weekly' ? 7 : 1);

            // Determine which video types to include based on digest settings
            $videoTypes = $digest->video_types ?? ['videos'];
            $perChannelLimit = (int)($options['limit'] ?? $maxVideosPerSource);

            $payloadOptions = [
                'maxVideosPerChannel' => in_array('videos', $videoTypes) ? $perChannelLimit : 0,
                'maxShortsPerChannel' => in_array('shorts', $videoTypes) ? $perChannelLimit : 0,
                'maxStreamsPerChannel' => in_array('streams', $videoTypes) ? $perChannelLimit : 0,
                'maxVideosPerSearch' => in_array('videos', $videoTypes) ? $perChannelLimit : 0,
                'maxShortsPerSearch' => in_array('shorts', $videoTypes) ? $perChannelLimit : 0,
                'maxStreamsPerSearch' => in_array('streams', $videoTypes) ? $perChannelLimit : 0,
                'channelDateFilterMode' => 'relative',
                'channelDaysBack' => (int)$daysBack,
                'channelSortBy' => $options['sort'] ?? 'latest',
                'searchDateFilter' => (int)$daysBack === 1 ? 'today' : ((int)$daysBack === 7 ? 'week' : 'month'),
                'searchSortBy' => 'relevance',
                'downloadSubtitles' => true,
                'includeTimestamps' => true,
                'preferAutoSubtitles' => false,
            ];

            Log::info("Railway Analysis Payload for Custom Digest ID {$digest->id}:", [
                'urls' => $sourceUrls,
                'options' => $payloadOptions
            ]);

            $items = $railway->analyzeChannels($sourceUrls, $payloadOptions);

            if ($items === null) {
                throw new \Exception("Railway API failed or unreachable.");
            }

            Log::info("Step 1 Complete: Found " . count($items) . " videos from YouTube.");

            if (empty($items)) {
                Log::info("No new videos found for custom digest {$digest->id}.");
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

                $transcript = $this->parseTranscript($item);
                $video->transcript = $transcript;
                
                if (!$video->duration && !empty($transcript)) {
                    $last = end($transcript);
                    $video->duration = (int) ceil(($last['start']??0) + ($last['duration']??0));
                }

                $channelUrl = $item['channelUrl'] ?? '';
                $channelName = $item['channel'] ?? $item['channelName'] ?? 'Unknown';
                $video->channel_title = $channelName;
                
                // Extract and save published date
                $publishedDate = $item['publishedTimeText'] ?? $item['publishedAt'] ?? $item['date'] ?? $item['publishedDate'] ?? null;
                if ($publishedDate) {
                    try {
                        // Convert to UTC so MySQL stores the correct UTC value.
                        $parsed = \Carbon\Carbon::parse($publishedDate);
                        $video->published_at = $parsed->utc();
                    } catch (\Exception $e) {
                        Log::warning("Failed to parse published date: {$publishedDate}");
                    }
                }

                $matchedChannel = $digest->channels->first(function($c) use ($channelUrl, $channelName) {
                    return (str_contains($channelUrl, $c->url)) || (stripos($c->name, $channelName) !== false);
                });
                
                if ($matchedChannel) {
                    $video->channel_id = $matchedChannel->id;
                }

                $video->save();
                $videosToSend[] = $video;
                Log::info(" - Video Saved: {$video->title}");
                
                $shouldSummarize = $includeSummary && (!empty($digest->custom_prompt) || empty($video->summary_detailed));
                
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
                $provider = config('services.ai.provider', 'openai');
                Log::info("Step 2/4: Summarizing " . count($videosToSummarize) . " videos using AI ({$provider})...");
                $chunks = array_chunk($videosToSummarize, 5);

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
                    'published_at' => ($video->published_at ?? $video->created_at)->format('M d, Y'),
                 ];
            }

            $processedCount = count($processedVideos);

            if (!empty($processedVideos)) {
                $run = \App\Models\DigestRun::create([
                    'digest_id' => $digest->id,
                    'user_id' => $user->id,
                    'batch_id' => $shareToken,
                    'summary_count' => count($processedVideos),
                    'total_duration' => collect($videosToSend)->sum('duration'),
                    'completed_at' => now(),
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

                Log::info("Step 3/4: Dispatching Email to {$user->email} via mailer: " . config('mail.default') . "...");
                try {
                    Mail::to($user->email)->send(new \App\Mail\CustomDigestMail($user, $digest, $processedVideos, now()->format('F j, Y'), $shareToken, $metrics));
                    Log::info("Step 4/4: Email sent successfully to {$user->email}.");
                } catch (\Exception $mailEx) {
                    Log::error("Step 4/4: FAILED to send email to {$user->email}: " . $mailEx->getMessage());
                }
                Log::info(">>> SUCCESS: Digest Process Completed: '{$digest->name}'");
            } else {
                Log::info("Step 3/4: Skipping Email (No new content discovered).");
                Log::info(">>> FINISHED: No content to send for '{$digest->name}'");
            }
        } catch (\Exception $e) {
            Log::error("Digest Processing Failed for ID {$digest->id}: " . $e->getMessage());
            Log::error($e->getTraceAsString());
        } finally {
            // Refund unused credits
            if ($estimatedCost > 0) {
                $actualUsed = $processedCount * $costPerVideo;
                $refund = max(0, $estimatedCost - $actualUsed);
                if ($refund > 0) {
                    $quotaManager->decrementUsage($user, 'youtube', $refund);
                    Log::info("Refunded {$refund} credits to {$user->email}.");
                }
            }
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
