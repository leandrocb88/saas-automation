<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApifyService
{
    protected string $baseUrl = 'https://api.apify.com/v2';
    protected ?string $token;

    public function __construct()
    {
        $this->token = config('services.apify.token');
    }

    /**
     * Trigger an Actor Run Synchronously
     */
    public function runActorSync(string $actorId, array $input = [], int $memoryMbytes = null): ?array
    {
        // Ensure ID uses tilde if provided in user/name format for some endpoints, 
        // but standard API usually accepts slash. 
        // However, run-sync docs often show username~actorname.
        // Let's rely on the passed ID.
        
        $url = "{$this->baseUrl}/acts/{$actorId}/run-sync";
        
        $queryParams = [
            'token' => $this->token,
            'timeout' => 300, // 5 minutes max
        ];

        if ($memoryMbytes) {
            $queryParams['memory'] = $memoryMbytes;
        }

        $response = Http::timeout(300)->post($url . '?' . http_build_query($queryParams), $input);

        // The run-sync endpoint returns the OUTPUT record directly (usually the result JSON)
        if ($response->successful()) {
            Log::info('Raw Apify Body: ' . $response->body());
            $data = $response->json();
            Log::info('Apify Sync Run Success', ['data_keys' => array_keys($data ?? []), 'is_array' => is_array($data)]);
            return $data;
        }

        Log::error('Apify Sync Run Failed', [
            'actor' => $actorId,
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        return null;
    }

    /**
     * Trigger an Actor Run
     */
    public function runActor(string $actorId, array $input = [], int $memoryMbytes = null): ?string
    {
        $url = "{$this->baseUrl}/acts/{$actorId}/runs";
        
        $queryParams = [
            'token' => $this->token,
        ];

        if ($memoryMbytes) {
            $queryParams['memory'] = $memoryMbytes;
        }

        $response = Http::post($url . '?' . http_build_query($queryParams), $input);

        if ($response->successful()) {
            return $response->json('data.id'); // Return Run ID
        }

        Log::error('Apify: Failed to trigger actor', [
            'actor' => $actorId,
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        return null;
    }

    /**
     * Get Results from a Dataset
     */
    public function getDatasetItems(string $datasetId): array
    {
        $url = "{$this->baseUrl}/datasets/{$datasetId}/items";
        
        $response = Http::get($url, [
            'token' => $this->token,
            'format' => 'json',
            'clean' => true,
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        return [];
    }

    /**
     * Check Run Status
     */
    public function getRun(string $runId): array
    {
        $url = "{$this->baseUrl}/actor-runs/{$runId}";
        
        $response = Http::get($url, [
            'token' => $this->token,
        ]);
        return $response->json('data') ?? [];
    }

    /**
     * Run Actor Synchronously and Get Dataset Items
     * https://docs.apify.com/api/v2#/reference/actors/run-collection/run-actor-synchronously-and-get-dataset-items
     */
    public function runActorSyncGetDatasetItems(string $actorId, array $input = []): array
    {
        // Check if actorId is actually a full URL (e.g. Standby Mode)
        if (str_starts_with($actorId, 'http')) {
            $url = $actorId;
        } else {
            $url = "{$this->baseUrl}/acts/{$actorId}/run-sync-get-dataset-items";
        }
        
        $queryParams = [
            'token' => $this->token,
        ];

        // Only add timeout for standard API calls, not direct Container URLs
        if (!str_starts_with($actorId, 'http')) {
             $queryParams['timeout'] = 300; // 5 minutes max
        }

        try {
            // Handle existing query params in URL
            $separator = str_contains($url, '?') ? '&' : '?';
            $fullUrl = $url . $separator . http_build_query($queryParams);

            // This endpoint expects the input as the body
            // Explicitly use asJson() to ensure Content-Type: application/json
            $response = Http::asJson()
                ->timeout(300)
                ->post($fullUrl, $input);

            if ($response->successful()) {
                $data = $response->json();
                
                // Standby Actor returns wrapped response { items: [...] }
                if (is_array($data) && isset($data['items'])) {
                    return $data['items'];
                }
                
                return $data ?? [];
            }

            Log::error('Apify Sync/Dataset Run Failed', [
                'actor' => $actorId,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
        } catch (\Exception $e) {
            Log::error('Apify Connection Error', [
                'actor' => $actorId,
                'message' => $e->getMessage()
            ]);
        }

        return [];
    }
}
