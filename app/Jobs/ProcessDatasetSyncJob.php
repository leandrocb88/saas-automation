<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessDatasetSyncJob implements ShouldQueue
{
    use Queueable;

    protected string $s3FileIdOrActorId;
    protected int $localDatasetId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $s3FileIdOrActorId, int $localDatasetId)
    {
        $this->s3FileIdOrActorId = $s3FileIdOrActorId;
        $this->localDatasetId = $localDatasetId;
    }

    /**
     * Execute the job.
     */
    public function handle(\App\Services\YoutubeService $youtube, \App\Services\DatasetService $datasetService): void
    {
        $dataset = \App\Models\Dataset::find($this->localDatasetId);
        
        if (!$dataset) {
            \Illuminate\Support\Facades\Log::error("ProcessDatasetSyncJob: Dataset not found: {$this->localDatasetId}");
            return;
        }

        \Illuminate\Support\Facades\Log::info("ProcessDatasetSyncJob: Processing results for Dataset '{$dataset->name}' (#{$dataset->id})");

        // 1. Fetch items from S3 or Actor results endpoint
        // If the ID smells like an Apify dataset ID (usually 17 chars alphanumeric) 
        // Or if driver is Apify, we should query Apify.
        // For our S3 refactor, it ends with .json
        $items = [];
        if (str_ends_with($this->s3FileIdOrActorId, '.json')) {
            try {
                $fileContent = \Illuminate\Support\Facades\Storage::disk('s3')->get($this->s3FileIdOrActorId);
                if ($fileContent) {
                    $items = json_decode($fileContent, true) ?? [];
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("ProcessDatasetSyncJob: Failed to read from S3: " . $e->getMessage());
            }
        } else {
            $items = $youtube->getDatasetItems($this->s3FileIdOrActorId);
        }
        
        if (empty($items)) {
            \Illuminate\Support\Facades\Log::info("ProcessDatasetSyncJob: No items found in source {$this->s3FileIdOrActorId}");
            $dataset->update(['status' => 'idle', 'last_synced_at' => now()]);
            return;
        }

        Log::info("ProcessDatasetSyncJob: Found " . count($items) . " items. Processing videos...");

        // 2. Process videos
        $processedVideos = [];
        foreach ($items as $item) {
            $videoId = $youtube->extractVideoId($item['url'] ?? $item['videoUrl'] ?? '');
            if (!$videoId) continue;

            $video = \App\Models\Video::firstOrNew([
                'user_id' => $dataset->user_id,
                'video_id' => $videoId,
            ]);

            $video->title = $item['title'] ?? 'Unknown';
            $video->channel_title = $item['channelName'] ?? $item['channel'] ?? 'Unknown';
            $video->thumbnail_url = $item['thumbnailUrl'] ?? "https://img.youtube.com/vi/{$videoId}/mqdefault.jpg";
            $video->transcript = $youtube->parseTranscript($item);
            
            $publishedDate = $item['publishedTimeText'] ?? $item['publishedAt'] ?? $item['date'] ?? null;
            if ($publishedDate) {
                try {
                    $video->published_at = \Carbon\Carbon::parse($publishedDate);
                } catch (\Exception $e) {}
            }

            $video->save();
            $processedVideos[] = $video;
        }

        // 3. Aggregate to Markdown Knowledge File
        // The service handles duplicate prevention using the dataset_videos pivot table
        $datasetService->appendVideosToKnowledge($dataset, $processedVideos);

        // 4. Finalize Dataset State
        $dataset->update([
            'status' => 'idle',
            'last_synced_at' => now(),
        ]);

        Log::info("ProcessDatasetSyncJob: Sync complete for Dataset #{$dataset->id}. Processed " . count($processedVideos) . " videos.");
    }
}
