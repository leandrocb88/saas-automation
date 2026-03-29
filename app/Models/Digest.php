<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Digest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'frequency',
        'scheduled_at',
        'day_of_week',
        'custom_prompt',
        'global_summary_prompt',
        'mode',
        'search_term',
        'is_active',
        'last_run_at',
        'last_time_change_at',
        'timezone',
        'video_types',
        'status',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'last_time_change_at' => 'datetime',
        'video_types' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function channels()
    {
        return $this->belongsToMany(Channel::class, 'digest_channels');
    }

    public function runs()
    {
        return $this->hasMany(DigestRun::class);
    }
}
