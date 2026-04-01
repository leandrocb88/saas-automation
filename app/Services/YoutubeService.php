<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YoutubeService
{
    public const CHUNK_SIZE = 50;

    protected $googleApiKey;
    protected $googleBaseUrl = 'https://www.googleapis.com/youtube/v3';
    
    protected string $driver;
    protected array $driverConfig;

    public function __construct()
    {
        $this->googleApiKey = config('services.youtube.key');
        $this->driver = config('services.youtube_actor.driver', 'railway');
        $this->driverConfig = config("services.youtube_actor.{$this->driver}", []);
    }

    /**
     * Trigger the Youtube Actor (Apify or Railway) for dataset sync.
     */
    public function triggerDatasetSync(array $payload): ?string
    {
        $this->injectDebugFlag($payload);

        if (config('app.debug') || config('services.youtube_actor.debug')) {
            Log::info("DEBUG: Knowledge Base Actor Payload ({$this->driver}):", $payload);
        } else {
            Log::info("Triggering Youtube Actor ({$this->driver}) Sync for Dataset.");
        }

        if ($this->driver === 'apify') {
            return $this->triggerApifyActor($payload);
        }

        return $this->triggerRailwayActor($payload);
    }

    /**
     * Trigger Apify Actor Run
     */
    protected function triggerApifyActor(array $payload): ?string
    {
        $actorId = $this->driverConfig['actor_id'];
        $token = $this->driverConfig['token'];
        $url = "https://api.apify.com/v2/acts/{$actorId}/runs?token={$token}";

        try {
            $response = Http::timeout(60)->post($url, $payload);

            if ($response->successful()) {
                $runId = $response->json('data.id');
                // For Apify, the "dataset ID" we want is often the default dataset of the run
                return $response->json('data.defaultDatasetId') ?? $runId;
            }

            Log::error("Apify Actor Trigger Failed", ['status' => $response->status(), 'body' => $response->body()]);
        } catch (\Exception $e) {
            Log::error("Apify Actor Exception", ['message' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Trigger Railway Actor
     */
    protected function triggerRailwayActor(array $payload): ?string
    {
        $baseUrl = $this->driverConfig['base_url'];
        $apiKey = $this->driverConfig['api_key'];

        $headers = $apiKey ? ['x-api-key' => $apiKey] : [];

        try {
            // Adjust timeout since the response should be fast (automatic return of "processing")
            $response = Http::withHeaders($headers)->timeout(30)->post($baseUrl, $payload);

            if ($response->successful()) {
                $data = $response->json();
                return $data['runId'] ?? $data['dataset_id'] ?? $data['id'] ?? $payload['runId'] ?? 'unknown_run';
            }

            Log::error("Railway Actor Trigger Failed", ['status' => $response->status(), 'body' => $response->body()]);
        } catch (\Exception $e) {
            Log::error("Railway Actor Exception", ['message' => $e->getMessage()]);
            throw $e;
        }

        return null;
    }

    /**
     * Fetch processed results from the actor.
     */
    public function getDatasetItems(string $datasetId): array
    {
        Log::info("Fetching Dataset Items from ({$this->driver}): {$datasetId}");

        if ($this->driver === 'apify') {
            return $this->getApifyDatasetItems($datasetId);
        }

        return $this->getRailwayDatasetItems($datasetId);
    }

    protected function getApifyDatasetItems(string $datasetId): array
    {
        $token = $this->driverConfig['token'];
        $url = "https://api.apify.com/v2/datasets/{$datasetId}/items?token={$token}&format=json&clean=true";

        try {
            $response = Http::timeout(300)->get($url);
            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::error("Apify Dataset Fetch Exception", ['message' => $e->getMessage()]);
        }

        return [];
    }

    protected function getRailwayDatasetItems(string $datasetId): array
    {
        $baseUrl = rtrim($this->driverConfig['base_url'], '/');
        $apiKey = $this->driverConfig['api_key'];
        $url = "{$baseUrl}/dataset/{$datasetId}";

        $headers = $apiKey ? ['x-api-key' => $apiKey] : [];

        try {
            $response = Http::withHeaders($headers)->timeout(300)->get($url);
            if ($response->successful()) {
                $data = $response->json();
                return $data['items'] ?? $data ?? [];
            }
        } catch (\Exception $e) {
            Log::error("Railway Dataset Fetch Exception", ['message' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Fetch transcripts for a list of URLs synchronously.
     */
    public function fetchTranscripts(array $urls, array $options = []): ?array
    {
        // For simplicity, we'll use the trigger + poll or triggerSync logic
        // But the user previously had a synchronous Railway fetch. 
        // We'll maintain that pattern if driver is Railway.
        
        if ($this->driver === 'railway') {
             return $this->fetchRailwayTranscripts($urls, $options);
        }

        // For Apify, we'll use a sync run (standard ApifyService logic)
        return $this->fetchApifyTranscripts($urls, $options);
    }

    protected function fetchRailwayTranscripts(array $urls, array $options = []): ?array
    {
        $allResults = [];
        $chunks = array_chunk($urls, self::CHUNK_SIZE);
        
        foreach ($chunks as $chunk) {
            $categorized = $this->categorizeUrls($chunk);
            $payload = array_merge($categorized, $options);
            
            $this->injectDebugFlag($payload);

            try {
                $baseUrl = $this->driverConfig['base_url'];
                $apiKey = $this->driverConfig['api_key'];
                $headers = $apiKey ? ['x-api-key' => $apiKey] : [];

                $response = Http::withHeaders($headers)->timeout(300)->post($baseUrl, $payload);

                if ($response->successful()) {
                    $items = $response->json()['items'] ?? [];
                    $allResults = array_merge($allResults, $items);
                } else {
                    return null;
                }
            } catch (\Exception $e) {
                return null;
            }
        }

        return $allResults;
    }

    protected function fetchApifyTranscripts(array $urls, array $options = []): ?array
    {
        $categorized = $this->categorizeUrls($urls);
        $payload = array_merge($categorized, $options);
        
        $this->injectDebugFlag($payload);
        $actorId = $this->driverConfig['actor_id'];
        $token = $this->driverConfig['token'];
        $url = "https://api.apify.com/v2/acts/{$actorId}/run-sync?token={$token}&timeout=300";

        try {
            $response = Http::timeout(300)->post($url, $payload);
            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::error("Apify Sync Run Exception", ['message' => $e->getMessage()]);
        }

        return null;
    }

    public function getChannelDetails(string $url)
    {
        // 1. Extract Channel ID or Handle
        $identifier = $this->extractIdentifier($url);
        
        if (!$identifier) {
            return null;
        }

        // 2. Fetch Channel Data
        $channelData = $this->fetchChannelData($identifier);

        if (!$channelData) {
            return null;
        }

        return [
            'youtube_channel_id' => $channelData['id'],
            'name' => $channelData['snippet']['title'] ?? 'Unknown Channel',
            'thumbnail_url' => $channelData['snippet']['thumbnails']['high']['url'] ?? $channelData['snippet']['thumbnails']['default']['url'] ?? null,
            'subscriber_count' => $this->formatSubscriberCount($channelData['statistics']['subscriberCount'] ?? 0),
        ];
    }

    /**
     * Fetch statistics (like subscriber count) for multiple channel IDs.
     * Max 50 IDs per request.
     */
    public function getChannelsStatistics(array $channelIds): array
    {
        if (empty($channelIds)) {
            return [];
        }

        $allStats = [];
        $chunks = array_chunk($channelIds, 50);

        foreach ($chunks as $chunk) {
            $response = Http::timeout(15)->get("{$this->googleBaseUrl}/channels", [
                'part' => 'statistics',
                'id' => implode(',', $chunk),
                'key' => $this->googleApiKey,
            ]);

            if ($response->successful()) {
                $items = $response->json()['items'] ?? [];
                foreach ($items as $item) {
                    $allStats[$item['id']] = $this->formatSubscriberCount($item['statistics']['subscriberCount'] ?? 0);
                }
            }
        }

        return $allStats;
    }

    protected function extractIdentifier($url)
    {
        // Handle: https://www.youtube.com/@Handle
        if (preg_match('/@([\w\-\.]+)/', $url, $matches)) {
            return ['type' => 'forHandle', 'value' => $matches[1]];
        }

        // Channel ID: https://www.youtube.com/channel/UC...
        if (preg_match('/channel\/(UC[\w-]+)/', $url, $matches)) {
            return ['type' => 'id', 'value' => $matches[1]];
        }

        // Custom URL (Legacy): https://www.youtube.com/c/CustomName or user
        // Often allows dots too
        if (preg_match('/(?:c\/|user\/)([\w\-\.]+)/', $url, $matches)) {
            return ['type' => 'forUsername', 'value' => $matches[1]];
        }
        
        return null;
    }

    protected function fetchChannelData($identifier)
    {
        if (!$this->googleApiKey) {
            Log::warning('YOUTUBE_API_KEY is missing.');
            throw new \Exception('YOUTUBE_API_KEY is missing. Please check your .env configuration.');
        }

        $params = [
            'part' => 'snippet,statistics',
            'key' => $this->googleApiKey,
        ];

        if ($identifier['type'] === 'id') {
            $params['id'] = $identifier['value'];
        } elseif ($identifier['type'] === 'forHandle') {
            $params['forHandle'] = '@' . $identifier['value'];
        } elseif ($identifier['type'] === 'forUsername') {
             $params['forUsername'] = $identifier['value'];
        }

        $response = Http::timeout(15)->get("{$this->googleBaseUrl}/channels", $params);

        if ($response->failed()) {
            Log::error("YouTube API Error: " . $response->body());
            $error = $response->json()['error']['message'] ?? $response->body();
            throw new \Exception("YouTube API Error: " . $error);
        }

        $data = $response->json();

        return $data['items'][0] ?? null;
    }

    protected function formatSubscriberCount($count)
    {
        if ($count >= 1000000) {
            return round($count / 1000000, 1) . 'M';
        }
        if ($count >= 1000) {
            return round($count / 1000, 1) . 'K';
        }
        return $count;
    }

    /**
     * Determine if a URL is a YouTube channel URL.
     */
    public function isChannelUrl(string $url): bool
    {
        return (bool)preg_match('/^https?:\/\/(www\.)?youtube\.com\/(channel\/|c\/|user\/|@)[\w\-\.]+/', $url);
    }

    /**
     * Categorize a list of items into startUrls (videos), channelUrls, and searchKeywords.
     */
    public function categorizeUrls(array $items): array
    {
        $startUrls = [];
        $channelUrls = [];
        $searchKeywords = [];

        foreach ($items as $item) {
            if (filter_var($item, FILTER_VALIDATE_URL)) {
                if ($this->isChannelUrl($item)) {
                    $channelUrls[] = $item;
                } else {
                    $startUrls[] = $item;
                }
            } else {
                $searchKeywords[] = $item;
            }
        }

        return [
            'startUrls' => $startUrls,
            'channelUrls' => $channelUrls,
            'searchKeywords' => $searchKeywords,
        ];
    }
    /**
     * Helper to extract 11-char Video ID from various YouTube URL formats.
     */
    public function extractVideoId($url)
    {
        if (empty($url)) return null;
        preg_match('/(?:v=|\/)([\w-]{11})(?:\?|&|$)/', $url, $matches);
        return $matches[1] ?? null;
    }

    /**
     * Helper to normalize transcript/subtitle data from actor results.
     */
    public function parseTranscript($item)
    {
        return $item['transcript'] ?? $item['subtitles'] ?? $item['transcription'] ?? [];
    }

    /**
     * Inject debug flag from config into actor payload.
     */
    protected function injectDebugFlag(array &$payload): void
    {
        if (config('app.debug') || config('services.youtube_actor.debug')) {
            $payload['debug'] = true;
        }
    }
}
