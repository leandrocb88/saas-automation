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
    protected $signature = 'app:process-custom-digests {--force : Run immediately for all active digests} {--digest= : Process specific digest ID}';
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
            $scheduledTime = Carbon::createFromFormat('H:i', $digest->scheduled_at, $digest->timezone ?? 'UTC');
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

        foreach ($toProcess as $digest) {
            $this->info("Dispatching custom digest job: {$digest->name}");
            \App\Jobs\ProcessCustomDigestJob::dispatch($digest);
            $digest->update(['last_run_at' => now()]);
        }

        $this->info('Custom Digest Jobs Dispatched.');
    }
}
