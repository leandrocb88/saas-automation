<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Video;
use App\Models\Channel;
use App\Services\ApifyService;
use App\Services\OpenAIService;
use App\Services\GeminiService;
use App\Services\QuotaManager;
use App\Mail\DailyDigest;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use Carbon\Carbon;

class ProcessDailyDigests extends Command
{
    protected $signature = 'app:process-daily-digests 
                            {--force : Run immediately without checking schedule} 
                            {--user= : Process only for specific user ID}
                            {--limit= : Total max videos to fetch across all channels}
                            {--sort= : Sort order (newest, oldest, relevance)}
                            {--days-back= : Number of days to look back}';
    protected $description = 'Process and send daily email digests for subscribed channels.';

    public function handle()
    {
        $this->info('Identifying users for Daily Digest...');

        $currentHour = Carbon::now()->format('H'); // 00-23
        $force = $this->option('force');
        $userId = $this->option('user');
        
        $query = User::query();

        if ($userId) {
            $query->where('id', $userId);
        } else {
             $query->whereHas('digestSchedule', function($q) use ($currentHour, $force) {
                $q->where('is_active', true);
                if (!$force) {
                    $q->where('preferred_time', 'like', "{$currentHour}:%");
                }
            });
        }

        $users = $query->get();

        if ($users->isEmpty()) {
             $this->info($force ? 'No active users found.' : 'No users scheduled for this hour.');
             return;
        }

        $options = [
            'limit' => $this->option('limit'),
            'sort' => $this->option('sort'),
            'days_back' => $this->option('days-back') ?? 1,
        ];

        foreach ($users as $user) {
            $this->info("Dispatching digest job for user: {$user->email}");
            \App\Jobs\ProcessUserDigestJob::dispatch($user, $options);
        }

        $this->info('Daily Digest Jobs Dispatched.');
    }
}
