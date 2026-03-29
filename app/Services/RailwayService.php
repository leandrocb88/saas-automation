<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RailwayService
{
    public const CHUNK_SIZE = 25;

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
        $allResults = [];
        $chunks = array_chunk($urls, self::CHUNK_SIZE);
        $totalChunks = count($chunks);

        foreach ($chunks as $idx => $chunk) {
            $currentChunkNum = $idx + 1;
            $categorized = $this->youtube->categorizeUrls($chunk);
            $payload = array_merge($categorized, $options);

            Log::info("Railway Batch ({$currentChunkNum}/{$totalChunks}) Payload:", [
                'keys' => array_keys($payload),
                'startUrls_count' => count($payload['startUrls'] ?? []),
                'channelUrls_count' => count($payload['channelUrls'] ?? []),
                'searchKeywords_count' => count($payload['searchKeywords'] ?? []),
            ]);

            try {
                $response = $this->getClient(300)->post($this->baseUrl, $payload);

                if ($response->successful()) {
                    $data = $response->json();
                    $items = $data['items'] ?? [];
                    $allResults = array_merge($allResults, $items);
                    Log::info("Railway Batch ({$currentChunkNum}/{$totalChunks}) Success: Found " . count($items) . " items.");
                } else {
                    Log::error("Railway Batch ({$currentChunkNum}/{$totalChunks}) Error", [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'chunk' => $chunk
                    ]);
                    return null; // Fail fast so fallback can take over
                }
            } catch (\Exception $e) {
                Log::error("Railway Batch ({$currentChunkNum}/{$totalChunks}) Exception", [
                    'message' => $e->getMessage(),
                    'chunk' => $chunk
                ]);
                return null;
            }
        }

        return $allResults;
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
        $allResults = [];
        $chunks = array_chunk($channelUrls, self::CHUNK_SIZE);
        $totalChunks = count($chunks);

        foreach ($chunks as $idx => $chunk) {
            $currentChunkNum = $idx + 1;
            $categorized = $this->youtube->categorizeUrls($chunk);
            $payload = array_merge($categorized, $options);

            Log::info("Railway Channel Batch ({$currentChunkNum}/{$totalChunks}) Payload:", [
                'keys' => array_keys($payload),
                'channelUrls_count' => count($payload['channelUrls'] ?? []),
            ]);

            try {
                $response = $this->getClient(300)->post($this->baseUrl, $payload);

                if ($response->successful()) {
                    $data = $response->json();
                    $items = $data['items'] ?? [];
                    $allResults = array_merge($allResults, $items);
                    Log::info("Railway Channel Batch ({$currentChunkNum}/{$totalChunks}) Success: Found " . count($items) . " items.");
                } else {
                    Log::error("Railway Channel Batch ({$currentChunkNum}/{$totalChunks}) Error", [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'chunk' => $chunk
                    ]);
                    return null;
                }
            } catch (\Exception $e) {
                Log::error("Railway Channel Batch ({$currentChunkNum}/{$totalChunks}) Exception", [
                    'message' => $e->getMessage(),
                    'chunk' => $chunk
                ]);
                return null;
            }
        }

        return $allResults;
    }
}
