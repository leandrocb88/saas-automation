<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RailwayService
{
    protected string $baseUrl;
    protected ?string $apiKey;
    protected YouTubeService $youtube;

    public function __construct(YouTubeService $youtube)
    {
        $this->baseUrl = config('services.railway.base_url');
        $this->apiKey = config('services.railway.api_key');
        $this->youtube = $youtube;
    }

    protected function getClient(int $timeout = 60)
    {
        $headers = [];
        if ($this->apiKey) {
            $headers['x-api-key'] = $this->apiKey;
        }
        
        return Http::withHeaders($headers)->timeout($timeout);
    }

    /**
     * Fetch transcripts for a list of URLs.
     *
     * @param array $urls
     * @param array $options Additional options (include_summary, include_timestamps, etc.)
     * @return array|null Returns results array on success, null on API failure.
     */
    public function fetchTranscripts(array $urls, array $options = []): ?array
    {
        $categorized = $this->youtube->categorizeUrls($urls);
        $payload = array_merge($categorized, $options);

        // Debug Log: Ensure the payload keys are exactly what we expect
        Log::info("Railway Transaction Payload:", [
            'keys' => array_keys($payload),
            'startUrls_count' => count($payload['startUrls'] ?? []),
            'channelUrls_count' => count($payload['channelUrls'] ?? []),
            'searchKeywords_count' => count($payload['searchKeywords'] ?? []),
        ]);

        try {
            $response = $this->getClient(60)->post($this->baseUrl, $payload);

            if ($response->successful()) {
                $data = $response->json();
                return $data['items'] ?? [];
            }

            Log::error("Railway API Error", [
                'status' => $response->status(),
                'body' => $response->body(),
                'payload_keys' => array_keys($payload)
            ]);

            return null; // Return null to indicate API failure (not just empty results)
        } catch (\Exception $e) {
            Log::error("Railway API Exception", [
                'message' => $e->getMessage(),
                'payload_keys' => array_keys($payload)
            ]);
            return null;
        }
    }

    /**
     * Process channel analysis via Railway API.
     *
     * @param array $channelUrls
     * @param array $options (maxVideosPerChannel, daysBack, etc.)
     * @return array|null Returns results array on success, null on API failure.
     */
    public function analyzeChannels(array $channelUrls, array $options = []): ?array
    {
        $categorized = $this->youtube->categorizeUrls($channelUrls);
        $payload = array_merge($categorized, $options);

        Log::info("Railway Channel Analysis Payload:", [
            'keys' => array_keys($payload),
            'channelUrls_count' => count($payload['channelUrls'] ?? []),
        ]);

        try {
            $response = $this->getClient(120)->post($this->baseUrl, $payload);

            if ($response->successful()) {
                $data = $response->json();
                return $data['items'] ?? [];
            }

            Log::error("Railway Channel Analysis Error", [
                'status' => $response->status(),
                'body' => $response->body(),
                'payload_keys' => array_keys($payload)
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error("Railway Channel Analysis Exception", [
                'message' => $e->getMessage(),
                'payload_keys' => array_keys($payload)
            ]);
            return null;
        }
    }
}
