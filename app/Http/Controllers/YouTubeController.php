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
use Illuminate\Support\Facades\Storage;

class YouTubeController extends Controller
{
    protected $apify;
    protected $railway;
    protected $openai;
    protected $gemini;
    protected $quotaManager;
    protected $youtube;

    public function __construct(
        ApifyService $apify, 
        \App\Services\RailwayService $railway,
        OpenAIService $openai, 
        GeminiService $gemini,
        QuotaManager $quotaManager,
        \App\Services\YouTubeService $youtube
    ) {
        $this->apify = $apify;
        $this->railway = $railway;
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
        try {
            $details = $this->youtube->getChannelDetails($request->url);
        } catch (\Exception $e) {
            Log::error("Subscription Failed for URL {$request->url}: " . $e->getMessage());
            return back()->withErrors(['url' => 'Unable to subscribe. Please verify the URL or try again later.']);
        }

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

    public function toggleSubscriptionStatus(Request $request, Channel $channel)
    {
        if ($request->user()->id !== $channel->user_id) abort(403);
        
        $channel->update([
            'is_paused' => !$channel->is_paused
        ]);

        return back()->with('success', $channel->is_paused ? 'Channel paused.' : 'Channel resumed.');
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

        // Fetch all runs for this user to map them
        $runs = \App\Models\DigestRun::where('user_id', $user->id)->get()->keyBy('batch_id');

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

                 // Calculate Batch Metrics
                 $totalDurationSeconds = $videosByBatch->sum('duration') ?? 0;
                 $totalWords = $videosByBatch->sum(function($video) {
                     return str_word_count(strip_tags($video->summary_detailed ?? ''));
                 });
                 $readTimeSeconds = ceil(($totalWords / 200) * 60);
                 $timeSavedSeconds = max(0, $totalDurationSeconds - $readTimeSeconds);

                 // Link to DigestRun
                 $firstVideo = $videosByBatch->first();
                 $shareToken = $firstVideo->share_token ?? null;
                 $run = $shareToken ? ($runs[$shareToken] ?? null) : null;

                 $downloads = null;
                 if ($run) {
                     $downloads = [
                         'id' => $run->id,
                         'pdf_status' => $run->pdf_status,
                         'audio_status' => $run->audio_status,
                         'audio_duration' => $run->audio_duration,
                         'pdf' => route('digest_runs.pdf', $run->id),
                         'audio' => route('digest_runs.audio', $run->id),
                     ];
                 }

                 $formattedBatches[] = [
                     'time' => $timeStr,
                     'channels' => $formattedChannels,
                     'summaryMetrics' => [
                        'total_videos' => $videosByBatch->count(),
                        'total_duration' => $this->formatDuration($totalDurationSeconds) ?? '0s',
                        'read_time' => $this->formatDuration($readTimeSeconds) ?? '0s',
                        'time_saved' => $this->formatDuration($timeSavedSeconds) ?? '0s',
                     ],
                     'downloads' => $downloads,
                     'share_token' => $shareToken,
                 ];
             }
             
             // Sort batches desc (latest first)
             // ... existing comments ...

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

        usort($formattedChannels, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        // Calculate Summary Metrics
        $totalVideos = $videos->count();
        $totalDurationSeconds = $videos->sum('duration') ?? 0;
        
        $totalWords = $videos->sum(function($video) {
            return str_word_count(strip_tags($video->summary_detailed ?? ''));
        });
        
        $readTimeSeconds = ceil(($totalWords / 200) * 60);
        $timeSavedSeconds = max(0, $totalDurationSeconds - $readTimeSeconds);

        $summaryMetrics = [
            'total_videos' => $totalVideos,
            'total_duration' => $this->formatDuration($totalDurationSeconds) ?? '0s',
            'read_time' => $this->formatDuration($readTimeSeconds) ?? '0s',
            'time_saved' => $this->formatDuration($timeSavedSeconds) ?? '0s',
        ];

        // Find the digest run for downloads
        $run = \App\Models\DigestRun::where('batch_id', $token)->first();
        $downloads = null;
        if ($run) {
             $downloads = [
                 'id' => $run->id,
                 'pdf_status' => $run->pdf_status,
                 'audio_status' => $run->audio_status,
                 'audio_duration' => $run->audio_duration,
                 'pdf' => route('digest_runs.pdf', $run->id),
                 'audio' => route('digest_runs.audio', $run->id),
             ];
        }

        return Inertia::render('YouTube/DigestRun', [
            'digestDate' => $digestDate,
            'digestTime' => $digestTime,
            'channels' => $formattedChannels,
            'shareToken' => $token,
            'summaryMetrics' => $summaryMetrics,
            'downloads' => $downloads,
        ]);
    }

    public function forceDigest(Request $request)
    {
        $user = $request->user();
        if (!$user->subscribed('youtube')) {
             return back()->withErrors(['limit' => 'Daily Digest is only available for paid members.']);
        }

        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'days_back' => 'nullable|integer|min:1|max:30',
            'sort' => 'nullable|string|in:newest,oldest,relevance',
        ]);

        try {
            $params = [
                '--force' => true,
                '--user' => $user->id
            ];

            if (!empty($validated['limit'])) $params['--limit'] = $validated['limit'];
            if (!empty($validated['days_back'])) $params['--days-back'] = $validated['days_back'];
            if (!empty($validated['sort'])) $params['--sort'] = $validated['sort'];

            \Illuminate\Support\Facades\Artisan::call('app:process-daily-digests', $params);
            
            return back()->with('success', 'Digest processing started. check your email shortly.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to start digest process: ' . $e->getMessage()]);
        }
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
            try {
                if ($youtube->getChannelDetails($url)) {
                    $channelUrls[] = $url;
                } else {
                    $invalidChannels[] = "$url (Not Found)";
                }
            } catch (\Exception $e) {
                Log::error("Channel Validation Failed for URL {$url}: " . $e->getMessage());
                return back()->withErrors(['urls' => "Error validating channel: {$url}. Please verify URL."]);
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

        // 2. Trigger Railway API (with Apify fallback)
        $options = [
            'maxVideosPerChannel' => $maxVideosPerChannel,
            'maxShortsPerChannel' => 0,
            'maxStreamsPerChannel' => 0,
            'downloadSubtitles' => true,
            'enableSummary' => $request->boolean('include_summary'),
            'includeTimestamps' => $request->boolean('include_timestamps'),
            'preferAutoSubtitles' => false,
        ];

        if ($daysBack !== null) {
            $options['dateFilterMode'] = 'relative';
            $options['daysBack'] = $daysBack;
        }

        $items = $this->railway->analyzeChannels(array_values($channelUrls), $options);

        // Fallback to Apify ONLY if Railway API failed (returns null)
        if ($items === null) {
            Log::warning("Railway Channel Analysis failed, falling back to Apify.");

            $actorId = 'leandrocb88~youtube-video-transcript-actor';
            $input = array_merge([
                'channelUrls' => array_values($channelUrls),
            ], $options);

            try {
                $items = $this->apify->runActorSyncGetDatasetItems($actorId, $input);
            } catch (\Exception $e) {
                // Refund on Failure
                $quotaManager->decrementUsage($user, 'youtube', $estimatedCost);
                return back()->withErrors(['error' => 'Analysis failed: ' . $e->getMessage()]);
            }
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
                    $video->channel_title = $result['channel_title'] ?? null;

                    $video->thumbnail_url = $result['thumbnail'];
                    $video->transcript = $result['transcript'];
                    $video->summary_detailed = $result['summary'] ?? null;
                    $video->duration = $result['duration'] ?? 0;
                    $video->published_at = $result['published_at'] ?? null;
                    
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

                    // Dispatch Summary Generation Job
                    $video->update(['summary_status' => 'processing']);
                    \App\Jobs\GenerateVideoSummary::dispatch($video, 'detailed');

                    \Log::info("Channel Analysis: Saved video and dispatched summary job", ['video_id' => $videoId, 'title' => $result['title']]);
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

        // Filter out any invalid/empty videos before returning
        $newResults = array_filter($newResults, function($v) {
            return $this->isVideoValid($this->formatVideoRecordForValidation($v));
        });

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
                     // If video has no summary, dispatch a job to generate it
                     if (empty($video->summary_detailed) && $video->summary_status !== 'processing') {
                         $video->update(['summary_status' => 'processing']);
                         \App\Jobs\GenerateVideoSummary::dispatch($video, 'detailed');
                     }
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

        // 2. Trigger Fetching
        $items = $this->railway->fetchTranscripts(array_values($urlsToFetch), [
            'downloadSubtitles' => true,
            'enableSummary' => $request->boolean('include_summary'),
            'includeTimestamps' => $request->boolean('include_timestamps'),
            'preferAutoSubtitles' => false,
        ]);

        // Fallback to Apify ONLY if Railway API failed (returns null)
        if ($items === null) {
            Log::warning("Railway API failed, falling back to Apify.");
            
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
            $parsed = $this->parseVideoData($videoData);
            if ($this->isVideoValid($parsed)) {
                $newResults[] = $parsed;
            } else {
                Log::warning("Skipping empty/invalid video result", ['url' => $parsed['videoUrl'] ?? 'unknown']);
            }
        }

        // 3. Confirm & Refund Difference
        $actualCount = count($newResults);

        if ($actualCount === 0 && $existingVideos->isEmpty()) {
             // Refund
             if ($user) {
                 $quotaManager->decrementUsage($user, 'youtube', $cost);
             } else {
                 $quotaManager->decrementGuestUsage($ip, $userAgent, 'youtube', $cost);
             }
             return back()->withErrors(['urls' => 'Analysis failed. No videos found mapping your request.']);
        }

        // Ensure we don't refund more than cost
        $diff = max(0, $cost - $actualCount);
        
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
                'duration' => $result['duration'] ?? 0,
                'published_at' => $result['published_at'] ?? null,
                'summary_status' => 'processing',
            ]);

            // Dispatch Summary Generation Job
            \App\Jobs\GenerateVideoSummary::dispatch($video, 'detailed');

            $result['id'] = $video->id;
            $result['summary'] = null;
            $result['summary_short'] = null;
            $result['summary_detailed'] = null;
            $result['summary_status'] = 'processing';
        }
        unset($result); // Break reference
        
        // Merge New Results with Existing Cached Results
        $finalResults = array_merge($existingVideos->toArray(), $newResults);

        if (empty($finalResults)) {
            return back()->withErrors(['urls' => 'No new videos found and none in cache.']);
        }

        // Single result -> Go to video detail page
        if (count($finalResults) === 1) {
             $video = Video::find($finalResults[0]['id']);
             return to_route('youtube.show', $video);
        }

        // Multiple results -> Go to history
        return to_route('youtube.history');
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

        // Search logic
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('channel_title', 'like', "%{$search}%");
            });
        }

        // Sort logic
        $sort = $request->input('sort', 'newest');
        switch ($sort) {
            case 'older':
                $query->oldest();
                break;
            case 'alphabetical_asc':
                $query->orderBy('title', 'asc');
                break;
            case 'alphabetical_desc':
                $query->orderBy('title', 'desc');
                break;
            case 'newest':
            default:
                $query->latest();
                break;
        }

        $perPage = $request->input('per_page', 20);
        if (!in_array($perPage, [20, 40, 60, 100])) {
            $perPage = 20;
        }

        $videos = $query->select('id', 'video_id', 'title', 'channel_title', 'thumbnail_url', 'created_at', 'duration', 'published_at') // Lightweight select
            ->paginate($perPage)
            ->withQueryString()
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
                    'date' => ($video->published_at ?? $video->created_at)->format('M d, Y'),
                    'published_at' => $video->published_at ? $video->published_at->format('M j, Y') : null,
                    'duration_timestamp' => $this->formatDurationTimestamp($video->duration ?? 0),
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
        if ($video->user_id) {
            if (!$user) {
                return redirect()->route('login');
            }
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

        $video->update(['summary_status' => 'processing']);
        \App\Jobs\GenerateVideoSummary::dispatch($video, $type);

        // Deduct quota immediately if you want, or handle in Job. 
        // User requirements implied background generation, but quota usually happens at request.
        if ($user) {
            $quotaManager->incrementUsage($user, 'youtube', $summaryCost, 'ai_summary');
        } else {
             $ip = $request->ip();
             $userAgent = $request->userAgent();
             $quotaManager->incrementGuestUsage($ip, $userAgent, 'youtube', $summaryCost);
        }

        return response()->json(['status' => 'processing']);
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
        
        // Duration Fallback
        $duration = $videoData['duration'] ?? $videoData['lengthSeconds'] ?? 0;
        
        // Handle "MM:SS" or "HH:MM:SS" strings
        if (is_string($duration) && str_contains($duration, ':')) {
            $parts = array_reverse(explode(':', $duration));
            $seconds = 0;
            foreach ($parts as $index => $part) {
                $seconds += (int)$part * pow(60, $index);
            }
            $duration = $seconds;
        }

        if (!$duration && !empty($transcript)) {
             $last = end($transcript);
             $duration = ($last['start'] ?? 0) + ($last['duration'] ?? 0);
        }
        
        $videoUrl = $videoData['url'] ?? '';
        $videoId = $this->extractVideoId($videoUrl);

        $thumbnail = $videoData['thumbnailUrl'] ?? $videoData['thumbnail'] ?? null;
        if (!$thumbnail && $videoId) {
            $thumbnail = "https://img.youtube.com/vi/{$videoId}/mqdefault.jpg";
        }
        
        return [
            'videoUrl' => $videoUrl,
            'title' => ($videoData['title'] ?? '') ?: 'Unknown Video',
            'channel_title' => ($videoData['channel'] ?? $videoData['channelName'] ?? $videoData['author'] ?? '') ?: 'Unknown Channel',
            'thumbnail' => $thumbnail ?? '',
            'transcript' => $transcript,
            'duration' => $duration,
            'published_at' => $this->parseDate($videoData['publishedDate'] ?? $videoData['date'] ?? $videoData['published_at'] ?? null),
        ];
    }

    private function parseDate($dateString)
    {
        if (!$dateString || $dateString === 'N/A' || $dateString === 'Unknown') {
            return null;
        }

        try {
            return Carbon::parse($dateString);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function isVideoValid($parsedData)
    {
        $title = $parsedData['title'] ?? '';
        $transcript = $parsedData['transcript'] ?? [];
        
        // If title is missing or generic AND transcript is empty, it's invalid
        $isGenericTitle = in_array($title, ['N/A', 'Unknown Video', 'Unknown Title', '']);
        
        if ($isGenericTitle && empty($transcript)) {
            return false;
        }
        
        return true;
    }

    private function formatVideoRecordForValidation(Video $video)
    {
        return [
            'title' => $video->title,
            'transcript' => $video->transcript,
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

        // Calculate Transcript Read Time
        $fullText = '';
        if (is_array($video->transcript)) {
            $video->transcript = array_values($video->transcript); // Ensure indexed array
            $fullText = collect($video->transcript)->pluck('text')->join(' ');
        }
        
        $transcriptReadTime = null;
        if (!empty($fullText)) {
            $wordCount = str_word_count(strip_tags($fullText));
            $minutes = ceil($wordCount / 200);
            $transcriptReadTime = $minutes . ' min read';
        }

        // Calculate Summary Read Time
        $summaryReadTime = null;
        if (!empty($video->summary_detailed)) {
            $wordCount = str_word_count(strip_tags($video->summary_detailed));
            $minutes = ceil($wordCount / 200);
            $summaryReadTime = $minutes . ' min read';
        }

        return [
            'id' => $video->id,
            'videoUrl' => "https://www.youtube.com/watch?v={$video->video_id}",
            'title' => $video->title,
            'thumbnail' => $video->thumbnail_url,
            'transcript' => $video->transcript ?? [],
            'summary' => $video->summary_short,
            'summary_detailed' => $video->summary_detailed,
            'summary_status' => $video->summary_status,
            'channel_title' => $video->channel_title,
            'duration' => $this->formatDuration($video->duration ?? 0),
            'published_at' => $video->published_at ? $video->published_at->format('M j, Y') : null,
            'transcript_read_time' => $transcriptReadTime,
            'summary_read_time' => $summaryReadTime,
            'duration_timestamp' => $this->formatDurationTimestamp($video->duration ?? 0),
            'pdf_status' => $video->pdf_status ?? 'pending',
            'audio_status' => $video->audio_status ?? 'pending',
            'pdf_url' => $video->pdf_status === 'completed' ? route('video.pdf', $video->id) : null,
            'audio_url' => $video->audio_status === 'completed' ? route('video.audio', $video->id) : null,
            'audio_duration' => $video->audio_duration,
        ];
    }

    public function downloadVideoPdf(Video $video)
    {
        if ($video->user_id !== auth()->id()) abort(403);

        if ($video->pdf_status === 'completed' && $video->pdf_path && Storage::disk(config('filesystems.default', 'public'))->exists($video->pdf_path)) {
            return Storage::disk(config('filesystems.default', 'public'))->download($video->pdf_path);
        }

        if ($video->pdf_status === 'processing') {
            return response()->json(['status' => 'processing']);
        }

        $video->update(['pdf_status' => 'processing']);
        \App\Jobs\GenerateVideoPdf::dispatch($video);

        return response()->json(['status' => 'processing']);
    }

    public function downloadVideoAudio(Video $video)
    {
        if ($video->user_id !== auth()->id()) abort(403);

        if ($video->audio_status === 'completed' && $video->audio_path && Storage::disk(config('filesystems.default', 'public'))->exists($video->audio_path)) {
            return Storage::disk(config('filesystems.default', 'public'))->download($video->audio_path);
        }

        if ($video->audio_status === 'processing') {
             return response()->json(['status' => 'processing']);
        }

        $video->update(['audio_status' => 'processing']);
        \App\Jobs\GenerateVideoAudio::dispatch($video);
        
        return response()->json(['status' => 'processing']);
    }

    public function videoStatus(Request $request, Video $video)
    {
        $user = $request->user();

        // Policy Check — mirrors show() and generateSummary()
        if ($video->user_id) {
            if (!$user || $user->id !== $video->user_id) abort(403);
        } else {
            if ($video->session_id !== $this->getGuestId($request)) abort(403);
        }

        return response()->json([
            'pdf_status' => $video->pdf_status,
            'audio_status' => $video->audio_status,
            'summary_status' => $video->summary_status,
            'pdf_url' => $video->pdf_status === 'completed' ? route('video.pdf', $video->id) : null,
            'audio_url' => $video->audio_status === 'completed' ? route('video.audio', $video->id) : null,
            'audio_duration' => $video->audio_duration,
        ]);
    }

    private function formatDurationTimestamp($seconds)
    {
        if (!$seconds) return null;
        
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }
        
        return sprintf('%d:%02d', $minutes, $secs);
    }

    private function formatDuration($seconds)
    {
        if (!$seconds) return null;
        if ($seconds < 60) return "{$seconds}s";
        
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }
        
        return "{$minutes}m";
    }
}
