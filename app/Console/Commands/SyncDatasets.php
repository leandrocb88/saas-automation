<?php

namespace App\Console\Commands;

use App\Models\Dataset;
use App\Jobs\SyncDatasetJob;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SyncDatasets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-datasets {--force : Sync all active datasets immediately}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trigger daily sync for Knowledge Datasets/Personas.';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $force = $this->option('force');
        
        $query = Dataset::where('is_paused', false)
            ->where('status', '!=', 'syncing');

        $datasets = $query->get();

        if ($datasets->isEmpty()) {
            $this->info('No active datasets found for syncing.');
            return;
        }

        $triggeredCount = 0;

        foreach ($datasets as $dataset) {
            if (!$force) {
                if (!$this->isDue($dataset)) {
                    continue;
                }
            }

            $this->info("Triggering sync for Dataset #{$dataset->id}: {$dataset->name}");
            $this->triggerSync($dataset);
            $triggeredCount++;
        }

        $this->info("Successfully triggered {$triggeredCount} dataset syncs.");
    }

    /**
     * Determine if a dataset is due for syncing.
     */
    protected function isDue(Dataset $dataset): bool
    {
        $localNow = Carbon::now($dataset->timezone ?? 'UTC');
        $localTime = $localNow->format('H:i');

        // Check if we already synced today (local time)
        if ($dataset->last_synced_at) {
            $lastSyncedLocal = Carbon::parse($dataset->last_synced_at)->timezone($dataset->timezone ?? 'UTC');
            if ($lastSyncedLocal->isSameDay($localNow)) {
                return false;
            }
        }

        // Time window check (match Hour and Minute)
        $scheduledTime = Carbon::createFromFormat('H:i', $dataset->scheduled_time, $dataset->timezone ?? 'UTC');
        $diffInMinutes = $scheduledTime->diffInMinutes($localNow, false);

        // Window: 0 to 10 minutes past scheduled time
        return ($diffInMinutes >= 0 && $diffInMinutes <= 10);
    }

    /**
     * Trigger the background job with locking.
     */
    protected function triggerSync(Dataset $dataset)
    {
        // Atomic lock shared with Controller
        $lock = Cache::lock('sync-dataset-'.$dataset->id, 300);

        if (!$lock->get()) {
            Log::info("Scheduled sync skipped for Dataset #{$dataset->id}: Lock already held.");
            return;
        }

        if ($dataset->status === 'syncing') {
            Log::info("Scheduled sync skipped for Dataset #{$dataset->id}: Status is syncing.");
            $lock->release();
            return;
        }

        $dataset->update(['status' => 'syncing']);
        
        SyncDatasetJob::dispatch($dataset, false);
        
        $lock->release();
    }
}
