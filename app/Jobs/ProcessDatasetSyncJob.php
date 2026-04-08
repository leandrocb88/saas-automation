<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessDatasetSyncJob implements ShouldQueue
{
    use Queueable;

    protected string $s3FileIdOrActorId;
    protected int $localDatasetId;
    protected bool $isFullSync;

    /**
     * Create a new job instance.
     */
    public function __construct(string $s3FileIdOrActorId, int $localDatasetId, bool $isFullSync = false)
    {
        $this->s3FileIdOrActorId = $s3FileIdOrActorId;
        $this->localDatasetId = $localDatasetId;
        $this->isFullSync = $isFullSync;
    }

    /**
     * Execute the job.
     */
    public function handle(\App\Services\YoutubeService $youtube, \App\Services\DatasetService $datasetService): void
    {
        // Increase memory and time limits for large dataset processing
        ini_set('memory_limit', '1024M');
        set_time_limit(600); // 10 minutes

        \Illuminate\Support\Facades\Log::info("ProcessDatasetSyncJob STARTED: Looking for Dataset ID #{$this->localDatasetId}");
        $dataset = \App\Models\Dataset::find($this->localDatasetId);
        
        if (!$dataset) {
            \Illuminate\Support\Facades\Log::error("ProcessDatasetSyncJob ABORTED: Dataset #{$this->localDatasetId} not found in database.");
            return;
        }

        try {
            \Illuminate\Support\Facades\Log::info("ProcessDatasetSyncJob: Starting results processing for Dataset '{$dataset->name}' (#{$dataset->id})", [
                'source' => $this->s3FileIdOrActorId
            ]);

            // 1. Fetch items from S3 or Actor results endpoint
            $items = [];
            if (str_ends_with($this->s3FileIdOrActorId, '.json')) {
                \Illuminate\Support\Facades\Log::info("ProcessDatasetSyncJob: Fetching result file from S3: {$this->s3FileIdOrActorId}");
                try {
                    $fileContent = \Illuminate\Support\Facades\Storage::disk('s3')->get($this->s3FileIdOrActorId);
                    if ($fileContent) {
                        $items = json_decode($fileContent, true) ?? [];
                        unset($fileContent); // Free large string memory
                        \Illuminate\Support\Facades\Log::info("ProcessDatasetSyncJob: Successfully retrieved S3 content. Count: " . count($items));
                    } else {
                        \Illuminate\Support\Facades\Log::warning("ProcessDatasetSyncJob: S3 file is empty: {$this->s3FileIdOrActorId}");
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("ProcessDatasetSyncJob: Failed to read from S3: " . $e->getMessage());
                    throw $e; // Re-throw to be caught by the outer catch
                }
            } else {
                \Illuminate\Support\Facades\Log::info("ProcessDatasetSyncJob: Fetching items from Actor API: {$this->s3FileIdOrActorId}");
                $items = $youtube->getDatasetItems($this->s3FileIdOrActorId);
            }
            
            if (empty($items)) {
                \Illuminate\Support\Facades\Log::info("ProcessDatasetSyncJob: No items found in source {$this->s3FileIdOrActorId}. Finalizing as idle.");
                $dataset->update(['status' => 'idle', 'last_synced_at' => now()]);
                return;
            }

            \Illuminate\Support\Facades\Log::info("ProcessDatasetSyncJob: Found " . count($items) . " items. Processing in chunks... ");

            // 2. Process videos in chunks of 50 to keep memory stable for massive datasets
            $chunks = array_chunk($items, 50);
            unset($items); // Free raw items array memory after chunking

            foreach ($chunks as $index => $chunk) {
                \Illuminate\Support\Facades\Log::info("ProcessDatasetSyncJob: Processing chunk " . ($index + 1) . "/" . count($chunks));
                $processedVideos = [];
                
                foreach ($chunk as $item) {
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

                // 3. Append this chunk to knowledge base immediately
                if (!empty($processedVideos)) {
                    $datasetService->appendVideosToKnowledge($dataset, $processedVideos);
                }
                
                // Clear memory for the next chunk
                unset($processedVideos);
                if (function_exists('gc_collect_cycles')) gc_collect_cycles();
            }

            // 4. Finalize Dataset State
            $dataset->update([
                'status' => 'idle',
                'last_synced_at' => now(),
            ]);

            if ($this->isFullSync) {
                \Illuminate\Support\Facades\Log::info("ProcessDatasetSyncJob: Full Sync detected. Rebuilding entire markdown file...");
                $datasetService->regenerateKnowledgeFile($dataset);
            }

            \Illuminate\Support\Facades\Log::info("ProcessDatasetSyncJob: Mass-sync complete for Dataset #{$dataset->id}. Results finalized.");

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("ProcessDatasetSyncJob: CRITICAL FAILURE for Dataset #{$dataset->id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            $dataset->update(['status' => 'error']);
            throw $e; // Re-throw to allow job processor (Horizon/Forge) to handle retries if configured
        }
    }
}
