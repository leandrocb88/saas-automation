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

class ProcessCustomDigests extends Command
{
    protected $signature = 'app:process-custom-digests 
                            {--force : Run immediately for all active digests} 
                            {--digest= : Process specific digest ID}
                            {--limit= : Total max videos to fetch across all channels}
                            {--sort= : Sort order (newest, oldest, relevance)}
                            {--days-back= : Number of days to look back}
                            {--include-summary= : Whether to include AI Summaries}
                            {--sync : Run synchronously instead of queueing}
                            {--bypass-quota : Bypass credit check for testing}';
    protected $description = 'Process and send custom email digests.';

    public function handle()
    {
        $this->info('Identifying custom digests for processing...');

        $now = Carbon::now();
        $startTime = $now->copy()->subMinutes(10)->format('H:i');
        $endTime = $now->format('H:i');
        $currentDay = strtolower($now->format('D'));
        
        $force = $this->option('force');
        $digestId = $this->option('digest');

        $query = Digest::query()->where('is_active', true);

        if ($digestId) {
            $query->where('id', $digestId);
        }

        $digests = $query->get();

        $toProcess = $digests->filter(function ($digest) use ($force) {
            if ($force) return true;

            $localNow = Carbon::now($digest->timezone ?? 'UTC');
            $localTime = $localNow->format('H:i');
            $currentDay = strtolower($localNow->format('D'));

            // Check if scheduled time is within the last 10 minutes locally
            // scheduled_at may be stored as 'H:i:s' or 'H:i' — normalize to 'H:i'
            $scheduledHM = substr($digest->scheduled_at, 0, 5);
            $scheduledTime = Carbon::createFromFormat('H:i', $scheduledHM, $digest->timezone ?? 'UTC');
            $diffInMinutes = $scheduledTime->diffInMinutes($localNow, false);

            if ($diffInMinutes < 0 || $diffInMinutes > 10) {
                return false;
            }

            // Frequency check
            if ($digest->frequency === 'weekly' && $digest->day_of_week !== $currentDay) {
                return false;
            }

            // Anti-duplicate check (run at most once every 20 hours)
            return !$digest->last_run_at || $digest->last_run_at->lt(Carbon::now()->subHours(20));
        });

        if ($toProcess->isEmpty()) {
            $this->info('No digests due for processing.');
            return;
        }

        $includeSummaryOverride = $this->option('include-summary');

        foreach ($toProcess as $digest) {
            $options = [
                'limit'    => $this->option('limit'),
                'sort'     => $this->option('sort'),
                'days_back' => $this->option('days-back') ?? 1,
                'include_summary' => $includeSummaryOverride !== null
                    ? filter_var($includeSummaryOverride, FILTER_VALIDATE_BOOLEAN)
                    : true,
                'bypass_quota' => $this->option('bypass-quota'),
            ];

            $this->info("--------------------------------------------------");
            $this->info("Processing Digest #{$digest->id}: {$digest->name}");
            $this->info("User: {$digest->user->email}");
            $this->info("Options: " . json_encode($options));

            if ($this->option('sync')) {
                $this->info("Running synchronously...");
                \App\Jobs\ProcessCustomDigestJob::dispatchSync($digest, $options);
                $this->info("SUCCESS: Digest #{$digest->id} processed.");
            } else {
                \App\Jobs\ProcessCustomDigestJob::dispatch($digest, $options);
                $this->info("QUEUED: Digest job dispatched to background.");
            }

            $digest->update(['last_run_at' => now()]);
        }

        $this->info('Custom Digest Jobs Dispatched.');
    }
}
