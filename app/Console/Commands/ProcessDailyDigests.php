<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Digest;
use Carbon\Carbon;

class ProcessDailyDigests extends Command
{
    protected $signature = 'app:process-daily-digests 
                            {--force : Run immediately without checking schedule} 
                            {--user= : Process only for specific user ID}
                            {--limit= : Total max videos to fetch across all channels}
                            {--sort= : Sort order (newest, oldest, relevance)}
                            {--days-back= : Number of days to look back}
                            {--include-summary= : Whether to include AI Summaries}';
    protected $description = 'Process and send daily email digests for all active custom Digests.';

    public function handle()
    {
        $this->info('Identifying active Digests for processing...');

        $currentHour = Carbon::now()->format('H:i'); // e.g. "09:00"
        $force = $this->option('force');
        $userId = $this->option('user');

        $query = Digest::query()->where('is_active', true)->with('user', 'channels');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $digests = $query->get();

        $toProcess = $digests->filter(function ($digest) use ($force) {
            if ($force) return true;
            if ($digest->frequency !== 'daily') return false;

            $localNow = Carbon::now($digest->timezone ?? 'UTC');
            $localHour = $localNow->format('H');
            $scheduledHour = substr($digest->scheduled_at, 0, 2);

            // Match hour and ensure not run in last 20 hours
            return $localHour === $scheduledHour && 
                   (!$digest->last_run_at || $digest->last_run_at->lt(Carbon::now()->subHours(20)));
        });

        if ($toProcess->isEmpty()) {
            $this->info($force ? 'No active digests found.' : 'No digests due for processing in their respective timezones.');
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
            ];

            $this->info("Dispatching custom digest job for digest #{$digest->id} (User: {$digest->user->email})");
            \App\Jobs\ProcessCustomDigestJob::dispatch($digest, $options);
            $digest->update(['last_run_at' => now()]);
        }

        $this->info('Digest Jobs Dispatched.');
    }
}
