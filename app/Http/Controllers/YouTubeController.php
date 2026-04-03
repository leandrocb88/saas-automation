<?php

namespace App\Http\Controllers;

use App\Services\YoutubeService;
use App\Services\OpenAIService;
use App\Services\GeminiService;
use App\Services\QuotaManager;
use App\Models\Video;
use App\Models\Channel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Storage;

class YouTubeController extends Controller
{
    protected $openai;
    protected $gemini;
    protected $quotaManager;
    protected $youtube;

    public function __construct(
        YoutubeService $youtube,
        OpenAIService $openai, 
        GeminiService $gemini,
        QuotaManager $quotaManager
    ) {
        $this->youtube = $youtube;
        $this->openai = $openai;
        $this->gemini = $gemini;
        $this->quotaManager = $quotaManager;
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
        $query = $user->channels();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $sort = $request->input('sort', 'latest');
        if ($sort === 'name_asc') {
            $query->orderBy('name', 'asc');
        } elseif ($sort === 'name_desc') {
            $query->orderBy('name', 'desc');
        } else {
            $query->latest();
        }
        
        return Inertia::render('YouTube/Subscriptions', [
            'channels' => $query->paginate(12)->withQueryString(),
            'filters' => (object) $request->only(['search', 'sort']),
        ]);
    }

    public function youtubeAuthRedirect()
    {
        $redirectUrl = config('services.youtube.redirect', url('/auth/youtube/callback'));

        return \Laravel\Socialite\Facades\Socialite::driver('google')
            ->redirectUrl($redirectUrl)
            ->with(['access_type' => 'online', 'prompt' => 'consent select_account'])
            ->scopes(['https://www.googleapis.com/auth/youtube.readonly'])
            ->redirect();
    }

    public function youtubeAuthCallback(Request $request)
    {
        $redirectUrl = config('services.youtube.redirect', url('/auth/youtube/callback'));

        try {
            $socialUser = \Laravel\Socialite\Facades\Socialite::driver('google')
                ->redirectUrl($redirectUrl)
                ->user();
        } catch (\Exception $e) {
            Log::error('YouTube OAuth Callback Error: ' . $e->getMessage());
            return redirect()->route('youtube.subscriptions')->withErrors(['url' => 'Failed to connect to YouTube. Please try again.']);
        }

        $user = $request->user();
        $accessToken = $socialUser->token;

        // Fetch all subscriptions via YouTube Data API
        $channels = [];
        $pageToken = null;

        do {
            $params = [
                'part'         => 'snippet',
                'mine'         => 'true',
                'maxResults'   => 50,
                'access_token' => $accessToken,
            ];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $response = Http::timeout(15)->get('https://www.googleapis.com/youtube/v3/subscriptions', $params);

            if ($response->failed()) {
                Log::error('YouTube Subscriptions API Error', ['body' => $response->body()]);
                break;
            }

            $data = $response->json();

            foreach ($data['items'] ?? [] as $item) {
                $snippet = $item['snippet'] ?? [];
                $resourceId = $snippet['resourceId'] ?? [];
                $channelId = $resourceId['channelId'] ?? null;

                if (!$channelId) continue;

                $channels[] = [
                    'youtube_channel_id' => $channelId,
                    'url'                => 'https://www.youtube.com/channel/' . $channelId,
                    'name'               => $snippet['title'] ?? 'Unknown',
                    'thumbnail_url'      => $snippet['thumbnails']['high']['url']
                                            ?? $snippet['thumbnails']['default']['url']
                                            ?? null,
                ];
            }

            $pageToken = $data['nextPageToken'] ?? null;

        } while ($pageToken);

        $allStats = [];
        if (!empty($channels)) {
            $allIds = array_column($channels, 'youtube_channel_id');
            $allStats = $this->youtube->getChannelsStatistics($allIds);
        }

        $imported = 0;

        foreach ($channels as $ch) {
            $exists = $user->channels()->where('youtube_channel_id', $ch['youtube_channel_id'])->exists();

            if (!$exists) {
                $ch['subscriber_count'] = $allStats[$ch['youtube_channel_id']] ?? null;
                $user->channels()->create($ch);
                $imported++;
            }
        }

        return redirect()->route('youtube.subscriptions')
            ->with('success', "Imported {$imported} new channel(s) from your YouTube subscriptions." . (count($channels) - $imported > 0 ? ' ' . (count($channels) - $imported) . ' already existed.' : ''));
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
        // Detach from all digests before deleting
        \DB::table('digest_channels')->where('channel_id', $channel->id)->delete();
        $channel->delete();
        return back()->with('success', 'Unsubscribed.');
    }


    public function toggleSubscriptionStatus(Request $request, Channel $channel)
    {
        if ($request->user()->id !== $channel->user_id) abort(403);
        
        $channel->update([
            'is_paused' => !$channel->is_paused
        ]);

        return back()->with('success', $channel->is_paused ? 'Channel paused.' : 'Channel resumed.');
    }



    public function processChannel(Request $request)
    {
        $user = $request->user();
        $youtube = $this->youtube;
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
        $transcriptCost = $quotaManager->getCost($user, 'youtube', 'transcript');
        $summaryCost = $request->boolean('include_summary') ? $quotaManager->getCost($user, 'youtube', 'ai_summary') : 0;
        $costPerVideo = $transcriptCost + $summaryCost;
        
        $estimatedCost = $maxVideosPerChannel * count($channelUrls) * $costPerVideo;

        // 1. Check & Freeze Quota
        $remaining = $quotaManager->getRemainingQuota($user, 'youtube');
        if($remaining < $estimatedCost) {
            // How many videos can we afford if each costs $costPerVideo?
            if ($costPerVideo > 0) {
                $maxVideosTotal = (int)floor($remaining / $costPerVideo);
                $maxVideosPerChannel = (int)floor($maxVideosTotal / count($channelUrls));
            } else {
                $maxVideosTotal = 1000; // Cap total batch at 1000 if free
                $maxVideosPerChannel = (int)floor($maxVideosTotal / count($channelUrls));
            }
            
            if ($maxVideosPerChannel < 1) {
                return back()->withErrors(['limit' => "Not enough credits. Each video + summary requires {$costPerVideo} credits."]);
            }
            
            $estimatedCost = $maxVideosPerChannel * count($channelUrls) * $costPerVideo;
        }
        
        // Freeze estimated credits
        $quotaManager->incrementUsage($user, 'youtube', $estimatedCost, 'channel_analysis_freeze');

        // Allow script to continue running even if user disconnects
        ignore_user_abort(true);
        ini_set('memory_limit', '768M');
        set_time_limit(1800);

        // 2. Trigger Railway API (with Apify fallback)
        $options = [
            'maxVideosPerChannel' => $maxVideosPerChannel,
            'maxShortsPerChannel' => 0,
            'maxStreamsPerChannel' => 0,
            'downloadSubtitles' => true,
            'includeTimestamps' => $request->boolean('include_timestamps'),
            'preferAutoSubtitles' => false,
        ];
        
        $sortOrder = $request->sort_order;
        if ($sortOrder === 'date') $sortOrder = 'latest';
        $options['channelSortBy'] = $sortOrder;

        if ($daysBack !== null) {
            $options['channelDateFilterMode'] = 'relative';
            $options['channelDaysBack'] = $daysBack;
        }

        // 2. Trigger Fetching (Consolidated service handles driver/fallback)
        $items = $this->youtube->fetchTranscripts(array_values($channelUrls), $options);

        if (empty($items)) {
            // Refund on failure
            $this->quotaManager->incrementUsage($user, 'youtube', -$estimatedCost, 'channel_analysis_refund');
            return back()->withErrors(['error' => 'Channel analysis failed or no videos found. Please try again later.']);
        }

        if (empty($items)) {
            // Refund if no items returned
            $quotaManager->incrementUsage($user, 'youtube', -$estimatedCost, 'channel_analysis_refund');
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

                    // Dispatch Summary Generation Job only if requested
                    if ($request->boolean('include_summary')) {
                        $video->update(['summary_status' => 'processing']);
                        \App\Jobs\GenerateVideoSummary::dispatch($video, 'detailed');
                    }

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
            $quotaManager->incrementUsage($user, 'youtube', -$diff, 'channel_analysis_refund');
        }

        return to_route('youtube.history')->with('success', "{$actualCount} " . \Illuminate\Support\Str::plural('video', $actualCount) . " analyzed successfully!");
    }

    public function process(Request $request)
    {
        $youtube = $this->youtube;
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

        $transcriptCost = $quotaManager->getCost($user, 'youtube', 'transcript');
        $summaryCost = $request->boolean('include_summary') ? $quotaManager->getCost($user, 'youtube', 'ai_summary') : 0;
        $costPerVideo = $transcriptCost + $summaryCost;
        $cost = count($urlsToFetch) * $costPerVideo;
        $ip = $request->ip();
        $userAgent = $request->userAgent();

        // 1. Verify Quota (credits already frozen upfront via /freeze-credits endpoint)
        if ($user) {
            // Frozen upfront — just validate enough was available
            $remaining = $quotaManager->getRemainingQuota($user, 'youtube');
            // Note: remaining is already reduced because freeze happened. No additional deduction needed here.
        } else {
            $remaining = $quotaManager->getGuestRemainingQuota($ip, $userAgent, 'youtube');
            // Note: remaining is already reduced because freeze happened. No additional deduction needed here.
        }

        ini_set('memory_limit', '768M');
        set_time_limit(1800); 

        // 2. Trigger Fetching
        $items = $this->youtube->fetchTranscripts(array_values($urlsToFetch), [
            'downloadSubtitles' => true,
            'includeTimestamps' => $request->boolean('include_timestamps'),
            'preferAutoSubtitles' => false,
            'subtitleLanguage' => $request->input('subtitle_language', 'en'),
        ]);

        // 2. Trigger Fetching (Consolidated service handles driver and any necessary fallback internally)
        $items = $this->youtube->fetchTranscripts(array_values($urlsToFetch), [
            'downloadSubtitles' => true,
            'includeTimestamps' => $request->boolean('include_timestamps'),
            'preferAutoSubtitles' => false,
            'subtitleLanguage' => $request->input('subtitle_language', 'en'),
        ]);

        if (empty($items) && $existingVideos->isEmpty()) {
            Log::error("YouTube Process failed or no items returned.");
            // Refund
            if ($user) {
                $this->quotaManager->incrementUsage($user, 'youtube', -$cost, 'refund');
            } else {
                $this->quotaManager->incrementGuestUsage($ip, $userAgent, 'youtube', -$cost);
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
                 $quotaManager->incrementUsage($user, 'youtube', -$cost, 'refund');
             } else {
                 $quotaManager->incrementGuestUsage($ip, $userAgent, 'youtube', -$cost);
             }
             return back()->withErrors(['urls' => 'Analysis failed. No videos found mapping your request.']);
        }

        // Ensure we don't refund more than cost
        $actualCost = $actualCount * $costPerVideo;
        $diff = max(0, $cost - $actualCost);
        
        if ($diff > 0) {
            if ($user) {
                $quotaManager->incrementUsage($user, 'youtube', -$diff, 'refund');
            } else {
                $quotaManager->incrementGuestUsage($ip, $userAgent, 'youtube', -$diff);
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
                'summary_status' => $request->boolean('include_summary') ? 'processing' : 'pending',
                'guest_ip' => $user ? null : $request->ip(),
                'guest_ua' => $user ? null : $request->userAgent(),
            ]);

            // Dispatch Summary Generation Job only if requested
            if ($request->boolean('include_summary')) {
                \App\Jobs\GenerateVideoSummary::dispatch($video, 'detailed');
            }

            $result['id'] = $video->id;
            $result['transcript'] = $video->transcript;
            $result['summary_status'] = $request->boolean('include_summary') ? 'processing' : 'pending';
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
             return to_route('youtube.show', $video)->with('success', "Video analyzed successfully!");
        }

        // Multiple results -> Go to history
        $count = count($finalResults);
        return to_route('youtube.history')->with('success', "{$count} " . \Illuminate\Support\Str::plural('video', $count) . " analyzed successfully!");
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
        $retentionDays = 30; // Default for Free/Guest
        
        if ($user && $user->subscribed('youtube')) {
             $retentionDays = 365; // Members
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
            case 'published_newest':
                $query->orderBy('published_at', 'desc');
                break;
            case 'published_oldest':
                $query->orderBy('published_at', 'asc');
                break;
            case 'older':
                $query->oldest(); // sort by created_at ASC (app added date)
                break;
            case 'alphabetical_asc':
                $query->orderBy('title', 'asc');
                break;
            case 'alphabetical_desc':
                $query->orderBy('title', 'desc');
                break;
            case 'newest':
            default:
                $query->latest(); // sort by created_at DESC (app added date)
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
                    'date' => $video->created_at->toIso8601String(),
                    'published_at' => $video->published_at ? $video->published_at->toIso8601String() : null,
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

    public function digestShow(Request $request, string $token)
    {
        $user = $request->user();

        // 1. Try to find the DigestRun first as it's the definitive source of ownership
        $digestRun = \App\Models\DigestRun::where('batch_id', $token)->first();
        
        if ($digestRun) {
            if ($digestRun->user_id !== $user->id) {
                abort(403);
            }
            
            $videos = Video::where('share_token', $token)->oldest('id')->get();
            
            if ($videos->isEmpty() && $digestRun->status === 'processing') {
                return Inertia::render('YouTube/BatchSummary', [
                    'results' => [],
                    'isHistoryView' => false,
                    'error' => 'Videos for this digest run are still being processed.'
                ]);
            }
            
            return Inertia::render('YouTube/BatchSummary', [
                'results' => $videos->map(fn($v) => $this->formatVideoForView($v)),
                'isHistoryView' => false,
            ]);
        }

        // 2. Fallback: Search videos directly (handle legacy or manually tagged videos)
        $videos = Video::where('share_token', $token)->oldest('id')->get();
        if ($videos->isNotEmpty()) {
            if ($videos->first()->user_id !== $user->id) {
                abort(403);
            }
            return Inertia::render('YouTube/BatchSummary', [
                'results' => $videos->map(fn($v) => $this->formatVideoForView($v)),
                'isHistoryView' => false,
            ]);
        }

        abort(404, 'Digest results not found.');
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

        $summaryCost = $quotaManager->getCost($user, 'youtube', 'ai_summary'); // Cost per summary generation

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

        if ($video->summary_status === 'processing') {
            return back()->withErrors(['summary' => 'Summary generation is already in progress.']);
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
             
             // Ensure the video record has the guest info for potential refund
             $video->update([
                 'guest_ip' => $ip,
                 'guest_ua' => $userAgent,
             ]);
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => 'processing']);
        }

        return back()->with('success', 'Summary generation started.');
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
            'published_at' => $this->parseDate(
                $videoData['publishedTimeText'] ??
                $videoData['publishedAt'] ??
                $videoData['publishedDate'] ??
                $videoData['date'] ??
                $videoData['published_at'] ??
                null
            ),
        ];
    }

    private function parseDate($dateString)
    {
        if (!$dateString || $dateString === 'N/A' || $dateString === 'Unknown') {
            return null;
        }

        try {
            // Convert to UTC so the stored value is timezone-aware.
            // Without this, Carbon strips the offset (e.g. -07:00) and stores
            // the raw local time as if it were UTC, shifting the displayed date.
            return Carbon::parse($dateString)->utc();
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

        // Normalize Transcript
        $video->transcript = $this->normalizeTranscript($video->transcript);

        // Calculate Transcript Read Time
        $fullText = '';
        if (!empty($video->transcript)) {
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
            'published_at' => $video->published_at ? $video->published_at->toIso8601String() : null,
            'transcript_read_time' => $transcriptReadTime,
            'summary_read_time' => $summaryReadTime,
            'duration_timestamp' => $this->formatDurationTimestamp($video->duration ?? 0),
            'pdf_status' => $video->pdf_status ?? 'pending',
            'audio_status' => $video->audio_status ?? 'pending',
            'pdf_url' => $video->pdf_status === 'completed' ? route('video.pdf', $video->id) : null,
            'audio_url' => $video->audio_status === 'completed' ? route('video.audio', $video->id) : null,
            'audio_duration' => $video->audio_duration,
            'translations' => $video->translations,
            'transcript_translate_status' => \Illuminate\Support\Facades\Cache::get("video_{$video->id}_translate_transcript_status", null),
            'summary_translate_status' => \Illuminate\Support\Facades\Cache::get("video_{$video->id}_translate_summary_status", null),
        ];
    }

    private function normalizeTranscript($transcript)
    {
        if (is_string($transcript)) {
            $decoded = json_decode($transcript, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $transcript = $decoded;
            }
        }

        if (is_array($transcript)) {
            if (isset($transcript['transcript']) && is_array($transcript['transcript'])) {
                return array_values($transcript['transcript']);
            }
            if (isset($transcript['translatedTranscript']) && is_array($transcript['translatedTranscript'])) {
                return array_values($transcript['translatedTranscript']);
            }
            // Check if it's already a flat array of objects
            if (isset($transcript[0]) && is_array($transcript[0])) {
                return array_values($transcript);
            }
            
            // Try to find the first array in the associative array
            foreach ($transcript as $value) {
                if (is_array($value) && is_array($value[0] ?? null) && isset($value[0]['text'])) {
                    return array_values($value);
                }
            }
            return array_values($transcript);
        }

        return [];
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

    /**
     * Fast endpoint: deduct credits immediately when the analyze button is clicked.
     * Returns the new remaining quota so the frontend can update instantly.
     */
    public function freezeCredits(Request $request)
    {
        $user = $request->user();
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        $quotaManager = $this->quotaManager;

        $videoCount = max(1, (int) $request->input('video_count', 1));
        $includeSummary = $request->boolean('include_summary');
        $subtitleLanguage = $request->input('subtitle_language', 'en');

        $transcriptCost = $quotaManager->getCost($user, 'youtube', 'transcript');
        $summaryCost = $includeSummary ? $quotaManager->getCost($user, 'youtube', 'ai_summary') : 0;
        $costPerVideo = $transcriptCost + $summaryCost;
        $totalCost = $videoCount * $costPerVideo;

        if ($user) {
            $remaining = $quotaManager->getRemainingQuota($user, 'youtube');
            if ($remaining < $totalCost) {
                return response()->json(['error' => 'Insufficient credits.'], 422);
            }
            $quotaManager->incrementUsage($user, 'youtube', $totalCost, 'video_freeze_upfront');
            $user->refresh();
            return response()->json([
                'remaining' => $quotaManager->getRemainingQuota($user, 'youtube'),
                'cost' => $totalCost,
            ]);
        } else {
            $remaining = $quotaManager->getGuestRemainingQuota($ip, $userAgent, 'youtube');
            if ($remaining < $totalCost) {
                return response()->json(['error' => 'Daily free limit reached.'], 422);
            }
            $quotaManager->incrementGuestUsage($ip, $userAgent, 'youtube', $totalCost);
            return response()->json([
                'remaining' => $quotaManager->getGuestRemainingQuota($ip, $userAgent, 'youtube'),
                'cost' => $totalCost,
            ]);
        }
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

        $summaryReadTime = null;
        if (!empty($video->summary_detailed)) {
            $wordCount = str_word_count(strip_tags($video->summary_detailed));
            $minutes = ceil($wordCount / 200);
            $summaryReadTime = $minutes . ' min read';
        }

        return response()->json([
            'pdf_status' => $video->pdf_status,
            'audio_status' => $video->audio_status,
            'summary_status' => $video->summary_status,
            'pdf_url' => $video->pdf_status === 'completed' ? route('video.pdf', $video->id) : null,
            'audio_url' => $video->audio_status === 'completed' ? route('video.audio', $video->id) : null,
            'audio_duration' => $video->audio_duration,
            'transcript_translate_status' => \Illuminate\Support\Facades\Cache::get("video_{$video->id}_translate_transcript_status", 'completed'),
            'summary_translate_status' => \Illuminate\Support\Facades\Cache::get("video_{$video->id}_translate_summary_status", 'completed'),
            'summary' => $video->summary,
            'summary_detailed' => $video->summary_detailed,
            'summary_read_time' => $summaryReadTime,
            'translations' => $video->translations,
        ]);
    }

    public function translate(Request $request, Video $video)
    {
        set_time_limit(300); // 5 minutes max execution time for translation

        $user = $request->user();
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        $language = $request->input('language', 'en');
        $quotaManager = $this->quotaManager;

        if (!$video->transcript) {
            return response()->json(['error' => 'No transcript available to translate.'], 422);
        }

        if (isset($video->translations[$language]['transcript'])) {
            return response()->json([
                'success' => true,
                'status' => 'completed',
                'transcript' => $video->translations[$language]['transcript']
            ]);
        }

        // Cost: 1 credit for translation
        $cost = $quotaManager->getCost($user, 'youtube', 'transcript');

        if ($user) {
            $remaining = $quotaManager->getRemainingQuota($user, 'youtube');
            if ($remaining < $cost) {
                return response()->json(['error' => 'Insufficient credits.'], 422);
            }
            $quotaManager->incrementUsage($user, 'youtube', $cost, 'video_translate');
        } else {
            $remaining = $quotaManager->getGuestRemainingQuota($ip, $userAgent, 'youtube');
            if ($remaining < $cost) {
                return response()->json(['error' => 'Daily free limit reached.'], 422);
            }
            $quotaManager->incrementGuestUsage($ip, $userAgent, 'youtube', $cost);
        }

        \Illuminate\Support\Facades\Cache::put("video_{$video->id}_translate_transcript_status", 'processing', 300);
        \App\Jobs\TranslateVideoTranscript::dispatch($video, $language, $user, $ip, $userAgent, $cost);

        return response()->json([
            'success' => true,
            'status' => 'processing',
        ]);
    }

    public function translateSummary(Request $request, Video $video)
    {
        set_time_limit(300); // 5 minutes max execution time for translation

        $user = $request->user();
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        $language = $request->input('language', 'en');
        $quotaManager = $this->quotaManager;

        if (!$video->summary_detailed && !$video->summary_short) {
            return response()->json(['error' => 'No summary available to translate.'], 422);
        }

        if (isset($video->translations[$language]['summary_detailed'])) {
            return response()->json([
                'success' => true,
                'status' => 'completed',
                'summary_detailed' => $video->translations[$language]['summary_detailed']
            ]);
        }

        // Cost: 1 credit for translation
        $cost = $quotaManager->getCost($user, 'youtube', 'transcript');

        if ($user) {
            $remaining = $quotaManager->getRemainingQuota($user, 'youtube');
            if ($remaining < $cost) {
                return response()->json(['error' => 'Insufficient credits.'], 422);
            }
            $quotaManager->incrementUsage($user, 'youtube', $cost, 'summary_translate');
        } else {
            $remaining = $quotaManager->getGuestRemainingQuota($ip, $userAgent, 'youtube');
            if ($remaining < $cost) {
                return response()->json(['error' => 'Daily free limit reached.'], 422);
            }
            $quotaManager->incrementGuestUsage($ip, $userAgent, 'youtube', $cost);
        }

        \Illuminate\Support\Facades\Cache::put("video_{$video->id}_translate_summary_status", 'processing', 300);
        \App\Jobs\TranslateVideoSummary::dispatch($video, $language, $user, $ip, $userAgent, $cost);

        return response()->json([
            'success' => true,
            'status' => 'processing',
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
