<?php

namespace App\Jobs;

use App\Models\Dataset;
use App\Services\YoutubeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SyncDatasetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $dataset;
    protected $isFullSync;

    /**
     * Create a new job instance.
     */
    public function __construct(Dataset $dataset, bool $isFullSync = false)
    {
        $this->dataset = $dataset;
        $this->isFullSync = $isFullSync;
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->dataset->id))->releaseAfter(60)];
    }

    /**
     * Execute the job.
     */
    public function handle(YoutubeService $youtube): void
    {
        $runId = \Illuminate\Support\Str::uuid()->toString();
        $callbackUrl = route('webhooks.youtube.dataset-sync', ['dataset_id' => $this->dataset->id]);
        
        $input = [
            'runId' => $runId,
            'channelUrls' => [$this->dataset->channel_url],
            'downloadSubtitles' => true,
            'callback' => $callbackUrl,
        ];

        if ($this->isFullSync || is_null($this->dataset->last_synced_at)) {
            $input['channelDateFilterMode'] = 'none';
            $input['maxVideosPerChannel'] = 1000000;
            
            if (is_null($this->dataset->last_synced_at)) {
                Log::info("Initial Sync (Full History) triggered for Dataset #{$this->dataset->id}.");
            }
        } else {
            $daysBack = $this->dataset->getDaysSinceLastSync() + 1;
            $daysBack = min(90, $daysBack); 

            $input['channelDateFilterMode'] = 'relative';
            $input['channelDaysBack'] = $daysBack;
            $input['maxVideosPerChannel'] = 1000;
            
            Log::info("Smart Catch-up Background Job for Dataset {$this->dataset->id}: looking back {$daysBack} days.");
        }

        // Trigger actor
        $resultingRunId = $youtube->triggerDatasetSync($input);

        if (!$resultingRunId) {
            $this->dataset->update(['status' => 'error']);
            $errorMsg = "Failed to trigger YouTube Actor run for Dataset: {$this->dataset->id}";
            Log::error($errorMsg);
            throw new \Exception($errorMsg);
        }
    }
}
