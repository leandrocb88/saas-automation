<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Digest;
use App\Models\Video;
use App\Services\ApifyService;
use App\Services\OpenAIService;
use App\Services\GeminiService;
use App\Services\QuotaManager;
use App\Mail\CustomDigestMail; // We'll need to create this
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use Carbon\Carbon;

class ProcessDigests extends Command
{
    protected $signature = 'app:process-digests 
                            {--force : Run immediately for all active digests} 
                            {--digest= : Process specific digest ID}
                            {--limit= : Total max videos to fetch across all channels}
                            {--sort= : Sort order (newest, oldest, relevance)}
                            {--days-back= : Number of days to look back}
                            {--include-summary= : Whether to include AI Summaries}
                            {--sync : Run synchronously instead of queueing}
                            {--bypass-quota : Bypass credit check for testing}';
    protected $description = 'Consolidated command to process and send all active email digests.';

    public function handle()
    {
        Log::info("ProcessDigests: Identifying digests for processing...");

        $force = $this->option('force');
        $digestId = $this->option('digest');

        $query = Digest::query()->where('is_active', true);

        if ($digestId) {
            $query->where('id', $digestId);
        }

        $digests = $query->get();
        Log::info("ProcessDigests: Found " . $digests->count() . " active digests in DB.");

        $toProcess = $digests->filter(function ($digest) use ($force) {
            if ($force) return true;

            $localNow = Carbon::now($digest->timezone ?? 'UTC');
            $localTime = $localNow->format('H:i');
            $currentDay = strtolower($localNow->format('D'));

            // Normalize scheduled_at (handle H:i:s or H:i)
            $scheduledHM = substr($digest->scheduled_at, 0, 5);
            $scheduledTime = Carbon::createFromFormat('H:i', $scheduledHM, $digest->timezone ?? 'UTC');
            
            // diffInMinutes(..., false) is (localNow - scheduledTime)
            $diffInMinutes = $scheduledTime->diffInMinutes($localNow, false);

            Log::info("Checking Digest #{$digest->id} ({$digest->name}): LocalNow: {$localTime}, Sched: {$scheduledHM}, Diff: {$diffInMinutes}m, Freq: {$digest->frequency}");

            // 1. Time window check (0 to 10 minutes past scheduled time)
            if ($diffInMinutes < 0 || $diffInMinutes > 10) {
                return false;
            }

            // 2. Frequency / Day check
            if ($digest->frequency === 'weekly' && $digest->day_of_week !== $currentDay) {
                Log::info(" - Skipped: Weekly day mismatch (Local: {$currentDay}, Sched: {$digest->day_of_week})");
                return false;
            }

            // 3. Prevent double-dispatching within the same 10-minute window
            $alreadyRun = $digest->last_run_at && $digest->last_run_at->gt(now()->subMinutes(10));
            if ($alreadyRun) {
                Log::info(" - Skipped: Already dispatched in the last 10 minutes.");
                return false;
            }

            // 4. Free user trickery prevention: max 1 digest run per day
            // We use 'youtube' as primary service check, similar to HandleInertiaRequests
            // Technically some users might be zillow, but this script is specific to youtube digests.
            if (!$digest->user->subscribed('youtube')) {
                // Check if ANY digest for this user has run today in local time
                // To avoid multiple DB queries, we can query it or check relations if loaded, 
                // but since it's a cron job, a subquery is safest.
                $hasRunToday = Digest::where('user_id', $digest->user_id)
                    ->whereNotNull('last_run_at')
                    ->get()
                    ->contains(function ($d) use ($localNow) {
                        $dLocalRun = Carbon::parse($d->last_run_at)->timezone($d->timezone ?? 'UTC');
                        return $dLocalRun->isSameDay($localNow);
                    });

                if ($hasRunToday) {
                    Log::info(" - Skipped: Free user has already had a digest run today.");
                    return false;
                }
            }

            return true;
        });

        if ($toProcess->isEmpty()) {
            $this->info('No digests due for processing right now.');
            return;
        }

        $includeSummaryOverride = $this->option('include-summary');

        foreach ($toProcess as $digest) {
            $options = [
                'limit'    => $this->option('limit'),
                'sort'     => $this->option('sort'),
                'days_back' => $this->option('days-back') ?? ($digest->frequency === 'weekly' ? 7 : 1),
                'include_summary' => $includeSummaryOverride !== null
                    ? filter_var($includeSummaryOverride, FILTER_VALIDATE_BOOLEAN)
                    : true,
                'bypass_quota' => $this->option('bypass-quota'),
            ];

            $this->info("Processing Digest #{$digest->id}: {$digest->name}");
            Log::info("Dispatching Job for Digest #{$digest->id}: {$digest->name} (User: {$digest->user->email})");

            if ($this->option('sync')) {
                \App\Jobs\ProcessCustomDigestJob::dispatchSync($digest, $options);
            } else {
                \App\Jobs\ProcessCustomDigestJob::dispatch($digest, $options);
            }

            $digest->update(['last_run_at' => now()]);
        }

        $this->info('Digest processing complete.');
    }
}
