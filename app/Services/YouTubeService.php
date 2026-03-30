<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YouTubeService
{
    protected $apiKey;
    protected $baseUrl = 'https://www.googleapis.com/youtube/v3';

    public function __construct()
    {
        $this->apiKey = config('services.youtube.key');
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
            $response = Http::timeout(15)->get("{$this->baseUrl}/channels", [
                'part' => 'statistics',
                'id' => implode(',', $chunk),
                'key' => $this->apiKey,
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
        if (!$this->apiKey) {
            Log::warning('YOUTUBE_API_KEY is missing.');
            throw new \Exception('YOUTUBE_API_KEY is missing. Please check your .env configuration.');
        }

        $params = [
            'part' => 'snippet,statistics',
            'key' => $this->apiKey,
        ];

        if ($identifier['type'] === 'id') {
            $params['id'] = $identifier['value'];
        } elseif ($identifier['type'] === 'forHandle') {
            $params['forHandle'] = '@' . $identifier['value'];
        } elseif ($identifier['type'] === 'forUsername') {
             $params['forUsername'] = $identifier['value'];
        }

        $response = Http::timeout(15)->get("{$this->baseUrl}/channels", $params);

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
}
