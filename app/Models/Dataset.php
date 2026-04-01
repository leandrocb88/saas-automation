<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dataset extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'channel_url',
        'scheduled_time',
        'timezone',
        'last_synced_at',
        'status',
        'is_paused',
        'file_path',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'is_paused' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function videos()
    {
        return $this->belongsToMany(Video::class, 'dataset_videos');
    }

    /**
     * Calculate how many days back we should look to catch up.
     */
    public function getDaysSinceLastSync(): int
    {
        if (!$this->last_synced_at) {
            return 1; // Default to 1 day if never synced
        }

        return (int) now()->diffInDays($this->last_synced_at);
    }
}
