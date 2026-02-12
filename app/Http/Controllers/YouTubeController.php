<?php

namespace App\Http\Controllers;

use App\Services\ApifyService;
use App\Services\OpenAIService;
use App\Services\GeminiService;
use App\Services\QuotaManager;
use App\Models\Video;
use App\Models\Channel;
use App\Models\DigestSchedule;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;

class YouTubeController extends Controller
{
    protected $apify;
    protected $openai;
    protected $gemini;
    protected $quotaManager;
    protected $youtube;

    public function __construct(
        ApifyService $apify, 
        OpenAIService $openai, 
        GeminiService $gemini,
        QuotaManager $quotaManager,
        \App\Services\YouTubeService $youtube
    ) {
        $this->apify = $apify;
        $this->openai = $openai;
        $this->gemini = $gemini;
        $this->quotaManager = $quotaManager;
        $this->youtube = $youtube;
    }

    public function index()
    {
        return Inertia::render('YouTube/Home');
    }

    public function channel(Request $request)
    {
        $user = $request->user();
        $canSubmit = $user && $user->subscribed('youtube');

        return Inertia::render('YouTube/ChannelAnalysis', [
            'canSubmit' => $canSubmit,
        ]);
    }

    public function subscriptions(Request $request)
    {
        $user = $request->user();
        
        return Inertia::render('YouTube/Subscriptions', [
            'channels' => $user->channels()->get(),
            'schedule' => $user->digestSchedule()->first(),
        ]);
    }

    public function storeSubscription(Request $request)
    {
        $request->validate([
            'url' => 'required|url',
            'name' => 'nullable|string',
        ]);

        $user = $request->user();
        
        // Fetch Channel Details from API
        $details = $this->youtube->getChannelDetails($request->url);

        if (!$details) {
            return back()->withErrors(['url' => 'Channel not found or invalid URL. Please check the URL and try again.']);
        }

        $user->channels()->create([
            'url' => $request->url,
            'youtube_channel_id' => $details['youtube_channel_id'],
            'name' => $details['name'],
            'thumbnail_url' => $details['thumbnail_url'] ?? null,
            'subscriber_count' => $details['subscriber_count'] ?? null,
        ]);

        return back()->with('success', 'Channel Subscribed.');
    }

    public function destroySubscription(Request $request, Channel $channel)
    {
        if ($request->user()->id !== $channel->user_id) abort(403);
        $channel->delete();
        return back()->with('success', 'Unsubscribed.');
    }

    public function updateSchedule(Request $request)
    {
        $request->validate([
            'preferred_time' => 'required',
            'timezone' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $user = $request->user();
        
        $user->digestSchedule()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'preferred_time' => $request->preferred_time,
                'timezone' => $request->timezone,
                'is_active' => $request->is_active,
            ]
        );

        return back()->with('success', 'Schedule updated.');
    }

    public function digest(Request $request)
    {
        $user = $request->user();
        
        $digests = Video::with('channel')
            ->where('user_id', $user->id)
            ->where('source', 'digest')
            ->whereNotNull('digest_date')
            ->orderBy('digest_date', 'desc')
            ->get()
            ->groupBy(function($val) {
                // Group by Date first
                return $val->digest_date->format('Y-m-d');
            });

        $formattedDigests = [];
        foreach ($digests as $dateStr => $videosByDate) {
             
             // Group by Batch (Time) within the Date
             $batches = $videosByDate->groupBy(function($v) {
                 return $v->digest_date->format('g:i A');
             });

             $formattedBatches = [];
             foreach ($batches as $timeStr => $videosByBatch) {
                 
                 // Group by Channel within the Batch
                 $byChannel = $videosByBatch->groupBy('channel_id');
                 
                 $formattedChannels = [];
                 foreach ($byChannel as $channelId => $channelVideos) {
                     $channelName = $channelVideos->first()->channel ? $channelVideos->first()->channel->name : 'Unknown Channel';
                     $channelUrl = $channelVideos->first()->channel ? $channelVideos->first()->channel->url : null;
                     $channelThumbnail = $channelVideos->first()->channel ? $channelVideos->first()->channel->thumbnail_url : null;
                     
                     $formattedChannels[] = [
                         'id' => $channelId,
                         'name' => $channelName,
                         'url' => $channelUrl,
                         'thumbnail' => $channelThumbnail,
                         'videos' => $channelVideos->map(function($v) {
                             return $this->formatVideoForView($v);
                         })->values(),
                     ];
                 }

                 // Sort channels by name
                 usort($formattedChannels, function($a, $b) {
                     return strcmp($a['name'], $b['name']);
                 });

                 $formattedBatches[] = [
                     'time' => $timeStr,
                     'channels' => $formattedChannels,
                 ];
             }
             
             // Sort batches desc (latest first)
             // Since $batches is from Collection groupBy, keys are time strings. 
             // But original query was ordered by digest_date desc, so $batches should roughly be in order?
             // Not guaranteed if keys are strings. 
             // We can sort $formattedBatches by time if needed, but 'g:i A' is hard to sort.
             // Better to trust DB order -> collection order.
             // DB order was `orderBy('digest_date', 'desc')`. 
             // So `groupBy(date)` keeps order? Yes.
             // `groupBy(time)` keeps order? Yes. 
             // So first batch should be latest time.

             $formattedDigests[] = [
                 'date' => Carbon::parse($dateStr)->format('F j, Y'),
                 'batches' => $formattedBatches,
             ];
        }

        return Inertia::render('YouTube/Digest', [
            'digests' => $formattedDigests,
        ]);
    }

    public function showDigestRun(Request $request, string $token)
    {
        $user = $request->user();
        
        // Find videos with this share token that belong to the user
        $videos = Video::with('channel')
            ->where('user_id', $user->id)
            ->where('share_token', $token)
            ->orderBy('digest_date', 'desc')
            ->get();

        if ($videos->isEmpty()) {
            abort(404, 'Digest run not found');
        }

        // Get date and time from first video
        $firstVideo = $videos->first();
        $digestDate = $firstVideo->digest_date->format('F j, Y');
        $digestTime = $firstVideo->digest_date->format('g:i A');

        // Group by channel
        $byChannel = $videos->groupBy('channel_id');
        
        $formattedChannels = [];
        foreach ($byChannel as $channelId => $channelVideos) {
            $channelName = $channelVideos->first()->channel ? $channelVideos->first()->channel->name : 'Unknown Channel';
            $channelUrl = $channelVideos->first()->channel ? $channelVideos->first()->channel->url : null;
            $channelThumbnail = $channelVideos->first()->channel ? $channelVideos->first()->channel->thumbnail_url : null;
            
            $formattedChannels[] = [
                'id' => $channelId,
                'name' => $channelName,
                'url' => $channelUrl,
                'thumbnail' => $channelThumbnail,
                'videos' => $channelVideos->map(function($v) {
                    return $this->formatVideoForView($v);
                })->values(),
            ];
        }

        // Sort channels by name
        usort($formattedChannels, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return Inertia::render('YouTube/DigestRun', [
            'digestDate' => $digestDate,
            'digestTime' => $digestTime,
            'channels' => $formattedChannels,
            'shareToken' => $token,
        ]);
    }

    public function processChannel(Request $request)
    {
        $user = $request->user();
        $apify = $this->apify;
        $quotaManager = $this->quotaManager;
        $openAI = $this->openai;
        $gemini = $this->gemini;
        
        // Strict Guard: Must be authenticated and subscribed
        if (!$user || !$user->subscribed('youtube')) {
            return to_route('youtube.channel')->withErrors(['limit' => 'Channel analysis is only available for paid members.']);
        }
        // Default values if not present (since we removed checkboxes from frontend)
        $request->merge([
            'include_timestamps' => true,
            'include_summary' => $request->boolean('include_summary', false), // Default to false
        ]);

        $request->validate([
            'urls' => ['required', 'string'],
            'max_videos' => ['required', 'integer', 'min:1', 'max:100'],
            'sort_order' => ['required', 'string', 'in:date,viewCount,rating,relevance,title,videoCount'],
            'date_range' => ['required', 'string', 'in:any,today,week,month,year'],
            'include_summary' => ['boolean'],
            'include_timestamps' => ['boolean'],
        ]);

        // Parse Channel URLs
        $rawUrls = explode("\n", $request->urls);
        $inputUrls = array_filter(array_map('trim', $rawUrls));

        $channelUrls = [];
        $invalidChannels = [];
        $youtube = $this->youtube;

        foreach ($inputUrls as $url) {
            // 1. Format Check (Regex)
            // Valid formats: /channel/ID, /c/Name, /user/Name, /@Handle
            if (!preg_match('/^https?:\/\/(www\.)?youtube\.com\/(channel\/|c\/|user\/|@)[\w\-\.]+/', $url)) {
                 $invalidChannels[] = "$url (Invalid Format)";
                 continue;
            }

            // 2. Existence Check (API)
            if ($youtube->getChannelDetails($url)) {
                $channelUrls[] = $url;
            } else {
                $invalidChannels[] = "$url (Not Found)";
            }
        }

        if (!empty($invalidChannels)) {
             return back()->withErrors(['urls' => 'The following URL(s) are invalid: ' . implode(', ', $invalidChannels)]);
        }

        if (empty($channelUrls)) {
            return back()->withErrors(['urls' => 'Please provide at least one valid YouTube Channel URL.']);
        }

        // Limit Check (Batch Channels)
        if (count($channelUrls) > 10) {
            return back()->withErrors(['urls' => 'Channel Batch limit exceeded (Max 10).']);
        }

        // Map date range to daysBack
        $daysBack = match($request->date_range) {
            'today' => 1,
            'week' => 7,
            'month' => 30,
            'year' => 365,
            default => null, // 'any' = no filter
        };

        // Calculate estimated cost
        $maxVideosPerChannel = $request->max_videos;
        $estimatedCost = $maxVideosPerChannel * count($channelUrls);

        // 1. Check & Freeze Quota
        $remaining = $quotaManager->getRemainingQuota($user, 'youtube');
        if($remaining < $estimatedCost) {
            // Limit to available credits
            $maxVideosPerChannel = (int)floor($remaining / count($channelUrls));
            $estimatedCost = $maxVideosPerChannel * count($channelUrls);
            
            if ($estimatedCost === 0) {
                return back()->withErrors(['limit' => "Not enough credits. Need at least " . count($channelUrls) . " credits."]);
            }
        }
        
        // Freeze estimated credits
        $quotaManager->incrementUsage($user, 'youtube', $estimatedCost, 'channel_analysis_freeze');

        // Allow script to continue running even if user disconnects
        ignore_user_abort(true);
        set_time_limit(600);

        // 2. Trigger Apify Actor
        $actorId = 'leandrocb88~youtube-video-transcript-actor';
        $input = [
            'channelUrls' => array_values($channelUrls),
            'maxVideosPerChannel' => $maxVideosPerChannel,
            'maxShortsPerChannel' => 0,
            'maxStreamsPerChannel' => 0,
            'downloadSubtitles' => true,
            'enableSummary' => $request->boolean('include_summary'),
            'includeTimestamps' => $request->boolean('include_timestamps'),
            'preferAutoSubtitles' => false,
        ];

        // Add date filter if not 'any'
        if ($daysBack !== null) {
            $input['dateFilterMode'] = 'relative';
            $input['daysBack'] = $daysBack;
        }

        try {
            $items = $apify->runActorSyncGetDatasetItems($actorId, $input);
        } catch (\Exception $e) {
            // Refund on Failure
            $quotaManager->decrementUsage($user, 'youtube', $estimatedCost);
            return back()->withErrors(['error' => 'Analysis failed: ' . $e->getMessage()]);
        }

        if (empty($items)) {
            // Refund if no items returned
            $quotaManager->decrementUsage($user, 'youtube', $estimatedCost);
            return back()->withErrors(['urls' => 'No videos found matching your criteria.']);
        }

        // 3. Process Results & Save to Database
        \Log::info("Channel Analysis: Processing {count} items from Apify", ['count' => count($items)]);
        
        $newResults = [];
        $videosToSummarize = []; // [id => ['video' => $video, 'text' => $fullText]]

        foreach ($items as $videoData) {
            try {
                $result = $this->parseVideoData($videoData);
                
                // Save to database with source='channel_analysis'
                $videoId = $this->extractVideoId($result['videoUrl']);
                
                if ($videoId) {
                    // FORCE CREATE new entry for channel analysis
                    $video = new Video();
                    $video->user_id = $user->id;
                    $video->video_id = $videoId;
                    $video->source = 'channel_analysis';

                    $video->title = $result['title'];
                    $video->title = $result['title'];
                    $video->channel_title = $result['channel_title'] ?? null;

                    if (empty($newResults)) {
                         \Log::info("Channel Analysis Debug: First Item Keys", array_keys($videoData));
                         \Log::info("Channel Analysis Debug: Extracted Channel Title", ['title' => $video->channel_title]);
                    }

                    $video->thumbnail_url = $result['thumbnail'];
                    $video->transcript = $result['transcript'];
                    $video->summary_detailed = $result['summary'] ?? null;
                    
                    // Try to match channel
                    $channelName = $videoData['channel'] ?? '';
                    $channel = $user->channels->first(function($c) use ($channelName) {
                        if ($channelName && (stripos($c->name, $channelName) !== false || stripos($channelName, $c->name) !== false)) {
                            return true;
                        }
                        return false;
                    });
                    
                    if ($channel) {
                        $video->channel_id = $channel->id;
                    }
                    
                    $video->save();
                    $newResults[] = $video;

                    // --- Queue for Auto-Generate Summary if missing ---
                    if (!$video->summary_detailed) {
                         $fullText = '';
                         if (is_array($result['transcript'])) {
                             $fullText = collect($result['transcript'])->pluck('text')->join(' ');
                         }

                         if (!empty($fullText)) {
                             $videosToSummarize[$video->id] = ['video' => $video, 'text' => $fullText];
                         }
                    }

                    \Log::info("Channel Analysis: Saved video", ['video_id' => $videoId, 'title' => $result['title']]);
                } else {
                    \Log::warning("Channel Analysis: Could not extract video ID", ['url' => $result['videoUrl']]);
                }
            } catch (\Exception $e) {
                \Log::error("Channel Analysis: Failed to process video", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Continue processing other videos
                continue;
            }
        }

        // Execute Parallel Summaries
        if (!empty($videosToSummarize)) {
             set_time_limit(0); // Unlimited execution time for large batches
             $provider = config('services.ai.provider', 'openai');
             \Log::info("Channel Analysis: Generating summaries for " . count($videosToSummarize) . " videos in parallel using $provider");
             
             // Chunk to avoid rate limits (10 concurrent requests max per batch)
             $chunks = array_chunk($videosToSummarize, 10, true);
             
             foreach ($chunks as $chunkIndex => $chunk) {
                 \Log::info("Channel Analysis: Processing chunk " . ($chunkIndex + 1) . " of " . count($chunks));
                 
                 try {
                     $responses = Http::pool(function (Pool $pool) use ($chunk, $gemini, $openAI, $provider) {
                          foreach ($chunk as $id => $data) {
                              if ($provider === 'gemini') {
                                  $gemini->addToPool($pool, (string)$id, $data['text']);
                              } else {
                                  $openAI->addToPool($pool, (string)$id, $data['text']);
                              }
                          }
                     });
        
                     foreach ($responses as $id => $response) {
                          if ($response->ok() && isset($videosToSummarize[$id])) {
                               $video = $videosToSummarize[$id]['video'];
                               $summary = null;
                               
                               if ($provider === 'gemini') {
                                    $summary = $gemini->parseResponse($response);
                               } else {
                                    $summary = $openAI->parseResponse($response);
                               }
                               
                               if ($summary) {
                                   $video->summary_detailed = $summary;
                                   $video->save();
                               }
                          } else {
                               \Log::error("Channel Analysis: Async summary failed for video ID $id");
                          }
                     }
                 } catch (\Exception $e) {
                     \Log::error("Channel Analysis: Pool execution failed for chunk $chunkIndex: " . $e->getMessage());
                 }
                 
                 // Small delay between chunks
                 if ($chunkIndex < count($chunks) - 1) {
                     sleep(1);
                 }
             }
        }

        \Log::info("Channel Analysis: Processed {count} videos successfully", ['count' => count($newResults)]);

        // 4. Refund unused credits
        $actualCount = count($newResults);
        $diff = max(0, $estimatedCost - $actualCount);
        
        if ($diff > 0) {
            $quotaManager->decrementUsage($user, 'youtube', $diff);
        }

        return to_route('youtube.history')->with('success', "{$actualCount} videos analyzed successfully!");
    }

    public function process(Request $request)
    {
        $apify = $this->apify;
        $openAI = $this->openai;
        $gemini = $this->gemini;
        $quotaManager = $this->quotaManager;
        // Default values
        $request->merge([
            'include_timestamps' => true,
            'include_summary' => $request->boolean('include_summary', false),
        ]);

        $request->validate([
            'urls' => ['required', 'string'], // We'll expect newline separated string from textarea, or array? Frontend plan said "textarea to accept newline-separated URLs". So input is string, we explode it.
            'include_summary' => ['boolean'],
            'include_timestamps' => ['boolean'],
        ]);

        // Parse URLs
        $rawUrls = explode("\n", $request->urls);
        $urls = array_filter(array_map('trim', $rawUrls), function($url) {
            return !empty($url) && preg_match('/^(https?\:\/\/)?(www\.youtube\.com|youtu\.?be)\/.+$/', $url);
        });
        
        if (empty($urls)) {
             return back()->withErrors(['urls' => 'Please provide at least one valid YouTube URL.']);
        }

        // Limit Check (Batch Size)
        $user = auth()->user();
        if ($user && $user->subscribed('youtube')) {
             if (count($urls) > 100) {
                 return back()->withErrors(['urls' => 'Batch limit exceeded (Max 100).']);
             }
        } else {
             if (count($urls) > 1) {
                  return back()->withErrors(['urls' => 'Free plan is limited to 1 video at a time. Upgrade to Batch process.']);
             }
        }

        // Smart Caching Logic (For All Users)
        $existingVideos = collect();
        $urlsToFetch = $urls;

        $videoIds = [];
        $urlToIdMap = [];
        
        foreach ($urls as $url) {
            $id = $this->extractVideoId($url);
            if ($id) {
                $videoIds[] = $id;
                $urlToIdMap[$url] = $id; // Keep track of which URL maps to which ID
            }
        }

        if (!empty($videoIds)) {
            $query = Video::whereIn('video_id', $videoIds);
            
            if ($user) {
                $query->where('user_id', $user->id);
            } else {
                $query->where('session_id', $this->getGuestId($request));
            }

            $foundVideos = $query->get();
            
            $foundIds = $foundVideos->pluck('video_id')->toArray();

            // Filter out URLs that are already found
            $urlsToFetch = array_filter($urls, function($url) use ($urlToIdMap, $foundIds) {
                $id = $urlToIdMap[$url] ?? null;
                return !in_array($id, $foundIds);
            });
            
            // Map found videos to view format
            $existingVideos = $foundVideos->map(function($video) {
                return $this->formatVideoForView($video);
            });
        }

        // Optimization: If nothing to fetch, return early
        if (empty($urlsToFetch)) {
            // If strictly one result, show detail view (BatchSummary with 1 item)
             if ($existingVideos->count() === 1) {
                 // We can redirect to the history show route for a cleaner URL, OR just render.
                 // We can redirect to the history show route for a cleaner URL, OR just render.
                 // Redirecting is cleaner "go to that video page".
                 $query = Video::where('video_id', $this->extractVideoId(array_values($urls)[0])); // crude, but effective since we know it exists
                 
                 if ($user) {
                     $query->where('user_id', $user->id);
                 } else {
                     $query->where('session_id', $this->getGuestId($request));
                 }
                 
                 $video = $query->first();
                 if ($video) {
                     return to_route('youtube.show', $video);
                 }
             }

             // If multiple, user asked to "go to history page" or we show the batch results from cache.
             // Showing the batch results is better UX than dumping them to the full history list.
             // But the user specifically said: "go to that video page or history pahe when multiple videos"
             // Let's interpret "history page" as "The History Index".
             return to_route('youtube.history');
        }
        
        // --- Proceed with Fetching Missing URLs ---

        $cost = count($urlsToFetch) * config('credits.youtube.video_fetch', 1);
        $ip = $request->ip();
        $userAgent = $request->userAgent();

        // 1. Check & Freeze Quota
        if ($user) {
            $remaining = $quotaManager->getRemainingQuota($user, 'youtube');
            if($remaining < $cost) {
                 return back()->withErrors(['limit' => "Insufficient credits. This batch requires {$cost} credits, but you only have {$remaining} remaining."]);
            }
            // Freeze full amount
            $quotaManager->incrementUsage($user, 'youtube', $cost, 'video_fetch_freeze');
        } else {
             $remaining = $quotaManager->getGuestRemainingQuota($ip, $userAgent, 'youtube');
             if($remaining < $cost) {
                 return back()->withErrors(['limit' => 'Daily free limit reached for this batch size.']);
             }
             $quotaManager->incrementGuestUsage($ip, $userAgent, 'youtube', $cost);
        }

        set_time_limit(600); 

        // 2. Trigger Apify
        $actorId = 'leandrocb88~youtube-video-transcript-actor';
        $input = [
            'downloadSubtitles' => true,
            'enableSummary' => $request->boolean('include_summary'),
            'includeTimestamps' => $request->boolean('include_timestamps'),
            'preferAutoSubtitles' => false,
            'startUrls' => array_values($urlsToFetch),
        ];

        try {
            $items = $apify->runActorSyncGetDatasetItems($actorId, $input);
        } catch (\Exception $e) {
            // Refund on Failure
            if ($user) {
                $quotaManager->decrementUsage($user, 'youtube', $cost);
            } else {
                $quotaManager->decrementGuestUsage($ip, $userAgent, 'youtube', $cost);
            }
            return back()->withErrors(['error' => 'Analysis failed: ' . $e->getMessage()]);
        }

        if (empty($items) && $existingVideos->isEmpty()) {
            // Refund if no items returned (and we expected some)
            if ($user) {
                $quotaManager->decrementUsage($user, 'youtube', $cost);
            } else {
                $quotaManager->decrementGuestUsage($ip, $userAgent, 'youtube', $cost);
            }
            return back()->withErrors(['urls' => 'Analysis failed. No videos found.']);
        }

        // Process Results
        $newResults = [];
        foreach ($items as $videoData) {
            $newResults[] = $this->parseVideoData($videoData);
        }

        // 3. Confirm & Refund Difference
        $actualCount = count($newResults);
        // Ensure we don't refund more than cost (if Apify returned duplicates or something weird)
        $charged = $cost;
        $diff = max(0, $charged - $actualCount);
        
        if ($diff > 0) {
            if ($user) {
                $quotaManager->decrementUsage($user, 'youtube', $diff);
            } else {
                $quotaManager->decrementGuestUsage($ip, $userAgent, 'youtube', $diff);
            }
        }
        // Usage already incremented via "freeze" step.

        // 7. Save to Database (for authenticated users and guests)
        foreach ($newResults as &$result) {
            // Extract Video ID from URL
            $videoId = $this->extractVideoId($result['videoUrl']) ?? 'unknown';

            $video = Video::create([
                'user_id' => $user ? $user->id : null,
                'session_id' => $user ? null : $this->getGuestId($request),
                'video_id' => $videoId,
                'title' => $result['title'],
                'channel_title' => $result['channel_title'] ?? null,
                'thumbnail_url' => $result['thumbnail'],
                'transcript' => $result['transcript'],
            ]);

            // --- Auto-Generate Summary (Detailed Only) ---
            $fullText = '';
            if (is_array($result['transcript'])) {
                $videoDataArray = $result['transcript']; // It's already parsed as array in parseVideoData? 
                // Wait, parseVideoData returns 'transcript' as array of ['text', 'start', 'duration']
                $fullText = collect($videoDataArray)->pluck('text')->join(' ');
            }
            
            try {
                // Determine AI Provider
                $provider = config('services.ai.provider', 'openai');
                $summary = null;

                if ($provider === 'gemini') {
                    Log::info("Generating summary with Gemini for video {$videoId}");
                    $summary = $gemini->generateSummary($fullText, 'detailed');
                } else {
                    Log::info("Generating summary with OpenAI (GPT-5 Nano) for video {$videoId}");
                    $summary = $openAI->generateSummary($fullText, 'detailed');
                }

                $video->update(['summary_detailed' => $summary]);
            } catch (\Exception $e) {
                // Log error but don't fail the request, users can retry later if we keep that button (or we just accept it failed)
                // For now, let's just log it.
                \Illuminate\Support\Facades\Log::error("Auto-summary failed for video {$videoId}: " . $e->getMessage());
            }

            $result['id'] = $video->id;
            $result['summary'] = $video->summary_detailed; // Use detailed as main summary
            $result['summary_short'] = null;
            $result['summary_detailed'] = $video->summary_detailed;
        }
        unset($result); // Break reference
        
        // Merge New Results with Existing Cached Results
        $finalResults = array_merge($existingVideos->toArray(), $newResults);

        // Redirect based on outcome
        if (count($finalResults) === 1) {
             // Single result -> Go to details
             $video = Video::find($finalResults[0]['id']);
             return to_route('youtube.show', $video);
        } else {
             // Multiple results -> Go to history
             return to_route('youtube.history');
        }
    }

    public function destroy(Request $request, Video $video)
    {
        $user = $request->user();

        // Policy Check
        if ($user) {
            if ($user->id !== $video->user_id) abort(403);
        } else {
            if ($video->session_id !== $this->getGuestId($request)) abort(403);
        }

        $video->delete();

        return back()->with('success', 'Video deleted.');
    }

    public function clearHistory(Request $request)
    {
        $user = $request->user();
        
        $query = Video::query();

        if ($user) {
            $query->where('user_id', $user->id);
        } else {
            $query->where('session_id', $this->getGuestId($request));
        }

        $query->delete();

        return to_route('youtube.history')->with('success', 'History cleared.');
    }

    public function history(Request $request)
    {
        $user = $request->user();

        // Determine Retention Limit
        $retentionDays = 1; // Default for Free/Guest
        
        if ($user && $user->subscribed('youtube')) {
             $subscription = $user->subscription('youtube');
             $priceId = $subscription->stripe_price;
             $proPrices = config("plans.youtube.pro.prices", []);
             if (in_array($priceId, $proPrices)) {
                 $retentionDays = 90;
             } else {
                 $retentionDays = 30; // Plus
             }
        }

        if ($retentionDays === 0) {
             return Inertia::render('YouTube/History', [
                 'videos' => [],
                 'canViewHistory' => false,
             ]);
        }

        $cutoff = Carbon::now()->subDays($retentionDays);

        $query = Video::where('created_at', '>=', $cutoff);
        
        if ($user) {
            $query->where('user_id', $user->id);
        } else {
            $query->where('session_id', $this->getGuestId($request));
        }

        $perPage = $request->input('per_page', 20);
        if (!in_array($perPage, [20, 40, 60, 100])) {
            $perPage = 20;
        }

        $videos = $query->latest()
            ->select('id', 'video_id', 'title', 'channel_title', 'thumbnail_url', 'created_at') // Lightweight select
            ->paginate($perPage)
            ->through(function ($video) {
                $thumbnail = $video->thumbnail_url;
                if (!$thumbnail && $video->video_id) {
                     $thumbnail = "https://img.youtube.com/vi/{$video->video_id}/mqdefault.jpg";
                }
                return [
                    'id' => $video->id,
                    'title' => $video->title,
                    'channel' => $video->channel_title,
                    'thumbnail' => $thumbnail,
                    'date' => $video->created_at->format('M d, Y'),
                     // 'relative_date' => $video->created_at->diffForHumans(),
                ];
            });

        return Inertia::render('YouTube/History', [
            'videos' => $videos,
            'canViewHistory' => true,
            'retentionDays' => $retentionDays,
            'queryParams' => $request->query() ?: null,
        ]);
    }

    public function show(Request $request, Video $video)
    {
        $user = $request->user();

        // Policy Check
        if ($user) {
            if ($user->id !== $video->user_id) abort(403);
        } else {
            if ($video->session_id !== $this->getGuestId($request)) abort(403);
        }

        return Inertia::render('YouTube/BatchSummary', [
            'results' => [$this->formatVideoForView($video)], 
            'isHistoryView' => true,
        ]);
    }

    public function generateSummary(Request $request, Video $video)
    {
        $user = $request->user();
        $quotaManager = $this->quotaManager;
        $openAI = $this->openai;
        $gemini = $this->gemini;

        // Policy Check
        if ($user) {
            if ($user->id !== $video->user_id) abort(403);
        } else {
            if ($video->session_id !== $this->getGuestId($request)) abort(403);
        }

        $request->validate([
            'type' => ['sometimes', 'string', 'in:short,detailed'],
        ]);

        $summaryCost = config('credits.youtube.ai_summary', 1); // Cost per summary generation

        // Check quota (Logic remains same)
        if ($user) {
            if (!$quotaManager->checkQuota($user, 'youtube', $summaryCost)) {
                return back()->withErrors(['summary' => 'Insufficient credits. Please upgrade your plan.']);
            }
        } else {
            $ip = $request->ip();
            $userAgent = $request->userAgent();
            if (!$quotaManager->checkGuestQuota($ip, $userAgent, 'youtube', $summaryCost)) {
                return back()->withErrors(['summary' => 'Daily free limit reached.']);
            }
        }

        $type = $request->input('type', 'detailed');
        $column = $type === 'short' ? 'summary_short' : 'summary_detailed';

        // Construct full transcript text
        $fullText = '';
        if (is_array($video->transcript)) {
            $fullText = collect($video->transcript)->pluck('text')->join(' ');
        }

        try {
            $provider = config('services.ai.provider', 'openai');
            $summary = null;

            if ($provider === 'gemini') {
                 $summary = $gemini->generateSummary($fullText, $type);
            } else {
                 Log::info("Generating summary with OpenAI (GPT-5 Nano) for video");
                 $summary = $openAI->generateSummary($fullText, $type);
            }
            
            $video->update([$column => $summary]);
            
            // Increment usage with 'ai_summary' tracking
            if ($user) {
                $quotaManager->incrementUsage($user, 'youtube', $summaryCost, 'ai_summary');
            } else {
                 $ip = $request->ip();
                 $userAgent = $request->userAgent();
                 $quotaManager->incrementGuestUsage($ip, $userAgent, 'youtube', $summaryCost);
            }
            
            return back()->with('success', 'Summary generated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['summary' => 'Failed to generate summary: ' . $e->getMessage()]);
        }
    }

    private function parseVideoData($videoData) {
        $transcript = [];
        $rawTranscript = $videoData['transcript'] ?? $videoData['subtitles'] ?? null;
        if (is_array($rawTranscript)) {
             $lastStart = -1;
             $currentIndex = -1;
             foreach ($rawTranscript as $sub) {
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
        } elseif (!empty($videoData['fullText'])) {
             $transcript = [['text'=>$videoData['fullText'], 'start'=>0, 'duration'=>0]];
        }
        
        $videoUrl = $videoData['url'] ?? '';
        $videoId = $this->extractVideoId($videoUrl);

        $thumbnail = $videoData['thumbnailUrl'] ?? $videoData['thumbnail'] ?? null;
        if (!$thumbnail && $videoId) {
            $thumbnail = "https://img.youtube.com/vi/{$videoId}/mqdefault.jpg";
        }
        
        return [
            'videoUrl' => $videoUrl,
            'title' => $videoData['title'] ?? 'Unknown Video',
            'channel_title' => $videoData['channel'] ?? $videoData['channelName'] ?? $videoData['author'] ?? 'Unknown Channel',
            'thumbnail' => $thumbnail ?? '',
            'transcript' => $transcript,
        ];
    }

    private function extractVideoId($url)
    {
        preg_match('/(?:v=|\/)([\w-]{11})(?:\?|&|$)/', $url, $matches);
        return $matches[1] ?? null;
    }

    private function getGuestId(Request $request)
    {
        return md5($request->ip() . $request->userAgent());
    }

    private function formatVideoForView(Video $video)
    {
        $thumbnail = $video->thumbnail_url;
        if (!$thumbnail && $video->video_id) {
             $thumbnail = "https://img.youtube.com/vi/{$video->video_id}/mqdefault.jpg";
        }

        return [
            'id' => $video->id,
            'videoUrl' => "https://www.youtube.com/watch?v={$video->video_id}",
            'title' => $video->title,
            'channel_title' => $video->channel_title,
            'thumbnail' => $thumbnail,
            'transcript' => $video->transcript,
            'summary' => $video->summary_detailed, // Backwards compat for initial render check
            'summary_short' => $video->summary_short,
            'summary_detailed' => $video->summary_detailed,
        ];
    }
}
