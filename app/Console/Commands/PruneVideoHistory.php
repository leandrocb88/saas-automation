<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PruneVideoHistory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'videos:prune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune video history based on retention policies';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting video history pruning...');

        // 1. Prune Guest Videos (Expire at Midnight)
        // QuotaManager resets guest credits at midnight (Carbon::tomorrow()).
        // Therefore, any guest video created before *today* should be pruned to match the "fresh start" for the day.
        $deletedGuests = \App\Models\Video::whereNotNull('session_id')
            ->where('created_at', '<', now()->startOfDay())
            ->delete();
        $this->info("Deleted {$deletedGuests} guest videos (created before today).");

        // 2. Prune Free User Videos (Older than 1 day)
        // Find users who are NOT subscribed to 'youtube'
        // This query might be heavy if many users, but fine for now.
        // A better approach for scale: Process chunks or use a more efficient join.
        // For simple implementation:
        // Delete videos where user_id is set, user is not subscribed, and video is > 1 day.
        // Determining "not subscribed" efficiently in SQL with Cashier is complex.
        
        // Alternative: Iterate all videos > 1 day old and check user? Too slow.
        // Alternative: Iterate Users?
        
        // Let's do a slightly optimized approach:
        // Delete videos > 90 days (Global limit for Pro) first?
        $deletedOld = \App\Models\Video::where('created_at', '<', now()->subDays(90))->delete();
        $this->info("Deleted {$deletedOld} videos older than 90 days (Global Pro Limit).");

        // Now handle the 30 day limit (Plus) and 1 day limit (Free).
        // Iterate over users with videos older than 1 day but newer than 90 days?
        
        // Let's iterate chunks of users to check subscription status. 
        // This ensures correct Cashier logic usage.
        \App\Models\User::chunk(100, function ($users) {
            foreach ($users as $user) {
                // Check Plan
                $retentionDays = 1;
                if ($user->subscribed('youtube')) {
                    $subscription = $user->subscription('youtube');
                    $priceId = $subscription->stripe_price;
                    $proPrices = config("plans.youtube.pro.prices", []);
                    if (in_array($priceId, $proPrices)) {
                        $retentionDays = 90;
                    } else {
                        $retentionDays = 30;
                    }
                }

                if ($retentionDays < 90) {
                     \App\Models\Video::where('user_id', $user->id)
                        ->where('created_at', '<', now()->subDays($retentionDays))
                        ->delete();
                }
            }
        });

        $this->info('Pruning completed.');
    }
}
