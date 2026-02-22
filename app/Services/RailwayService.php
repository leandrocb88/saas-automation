<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RailwayService
{
    protected string $baseUrl;
    protected ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.railway.base_url');
        $this->apiKey = config('services.railway.api_key');
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
        $payload = array_merge([
            'startUrls' => $urls,
        ], $options);

        try {
            $response = $this->getClient(60)->post($this->baseUrl, $payload);

            if ($response->successful()) {
                $data = $response->json();
                return $data['items'] ?? [];
            }

            Log::error("Railway API Error", [
                'status' => $response->status(),
                'body' => $response->body(),
                'urls' => $urls
            ]);

            return null; // Return null to indicate API failure (not just empty results)
        } catch (\Exception $e) {
            Log::error("Railway API Exception", [
                'message' => $e->getMessage(),
                'urls' => $urls
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
        $payload = array_merge([
            'channelUrls' => $channelUrls,
        ], $options);

        try {
            $response = $this->getClient(120)->post($this->baseUrl, $payload);

            if ($response->successful()) {
                $data = $response->json();
                return $data['items'] ?? [];
            }

            Log::error("Railway Channel Analysis Error", [
                'status' => $response->status(),
                'body' => $response->body(),
                'channels' => $channelUrls
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error("Railway Channel Analysis Exception", [
                'message' => $e->getMessage(),
                'channels' => $channelUrls
            ]);
            return null;
        }
    }
}
