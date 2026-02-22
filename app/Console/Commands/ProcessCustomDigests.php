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
        $currentTime = $now->format('H:i');
        $currentDay = strtolower($now->format('D')); // mon, tue, etc.
        
        $force = $this->option('force');
        $digestId = $this->option('digest');

        $query = Digest::query()->where('is_active', true);

        if ($digestId) {
            $query->where('id', $digestId);
        } elseif (!$force) {
            $query->where('scheduled_at', $currentTime);
            $query->where(function($q) use ($currentDay) {
                $q->where('frequency', 'daily')
                  ->orWhere(function($sub) use ($currentDay) {
                      $sub->where('frequency', 'weekly')
                          ->where('day_of_week', $currentDay);
                  });
            });
        }

        $digests = $query->get();

        if ($digests->isEmpty()) {
            $this->info('No digests due for processing.');
            return;
        }

        foreach ($digests as $digest) {
            $this->info("Dispatching custom digest job: {$digest->name}");
            \App\Jobs\ProcessCustomDigestJob::dispatch($digest);
        }

        $this->info('Custom Digest Jobs Dispatched.');
    }
}
