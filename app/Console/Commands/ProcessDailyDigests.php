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
        } elseif (!$force) {
            // Match digests scheduled for the current hour AND not run in the last 20 hours
            $query->where('frequency', 'daily')
                  ->where('scheduled_at', 'like', substr($currentHour, 0, 2) . ':%')
                  ->where(function($q) {
                      $q->whereNull('last_run_at')
                        ->orWhere('last_run_at', '<', Carbon::now()->subHours(20));
                  });
        }

        $digests = $query->get();

        if ($digests->isEmpty()) {
            $this->info($force ? 'No active digests found.' : 'No digests scheduled for this hour.');
            return;
        }

        $includeSummaryOverride = $this->option('include-summary');

        foreach ($digests as $digest) {
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
