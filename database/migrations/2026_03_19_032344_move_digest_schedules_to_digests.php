<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate data before dropping the table
        if (Schema::hasTable('digest_schedules')) {
            $schedules = DB::table('digest_schedules')->get();

            foreach ($schedules as $schedule) {
                // Determine include_summary if the column existed from later migrations
                $includeSummary = true;
                if (Schema::hasColumn('digest_schedules', 'include_summary')) {
                    $includeSummary = $schedule->include_summary;
                }

                // Create the new cohesive digest
                $digestId = DB::table('digests')->insertGetId([
                    'user_id' => $schedule->user_id,
                    'name' => 'My Subscriptions Digest',
                    'frequency' => 'daily',
                    'scheduled_at' => substr($schedule->preferred_time, 0, 5), // '09:00:00' -> '09:00'
                    'mode' => 'channels',
                    'is_active' => $schedule->is_active,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Attach all current user subscriptions to this new digest
                $userChannels = DB::table('channels')
                    ->where('user_id', $schedule->user_id)
                    ->pluck('id');

                $digestChannels = [];
                foreach ($userChannels as $channelId) {
                    $digestChannels[] = [
                        'digest_id' => $digestId,
                        'channel_id' => $channelId,
                    ];
                }

                if (!empty($digestChannels)) {
                    DB::table('digest_channels')->insert($digestChannels);
                }
            }

            // After successful data migration, drop the table safely
            Schema::dropIfExists('digest_schedules');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-create the table if rolled back, but data loss will occur.
        Schema::create('digest_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->time('preferred_time')->default('09:00:00');
            $table->string('timezone')->default('UTC');
            $table->boolean('is_active')->default(true);
            $table->boolean('include_summary')->default(true);
            $table->timestamps();
        });
    }
};
