<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\YouTubeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateChannelStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'youtube:update-stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update subscriber counts for all unique YouTube channels in the database.';

    /**
     * Execute the console command.
     */
    public function handle(YouTubeService $youtubeService)
    {
        $this->info('Starting YouTube channel stats update...');

        // 1. Get unique YouTube Channel IDs
        $uniqueChannelIds = Channel::distinct()
            ->pluck('youtube_channel_id')
            ->filter()
            ->toArray();

        if (empty($uniqueChannelIds)) {
            $this->info('No channels found to update.');
            return 0;
        }

        $this->info('Found ' . count($uniqueChannelIds) . ' unique channels.');

        // 2. Fetch statistics in batches of 50
        try {
            $allStats = $youtubeService->getChannelsStatistics($uniqueChannelIds);

            // 3. Update the database
            $bar = $this->output->createProgressBar(count($allStats));
            $bar->start();

            foreach ($allStats as $channelId => $subscriberCount) {
                Channel::where('youtube_channel_id', $channelId)
                    ->update(['subscriber_count' => $subscriberCount]);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info('Successfully updated stats for ' . count($allStats) . ' channels.');

        } catch (\Exception $e) {
            $this->error('Error updating channel stats: ' . $e->getMessage());
            Log::error('UpdateChannelStats Command Error', ['error' => $e->getMessage()]);
            return 1;
        }

        return 0;
    }
}
