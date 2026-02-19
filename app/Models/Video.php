<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'video_id',
        'title',
        'channel_title',
        'thumbnail_url',
        'transcript',
        'summary_short',
        'summary_detailed',
        'channel_id',
        'digest_date',
        'source',
        'duration',
        'pdf_path',
        'audio_path',
        'pdf_status',
        'audio_status',
        'audio_duration',
        'summary_status',
        'published_at',
    ];

    protected $casts = [
        'transcript' => 'array',
        'digest_date' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }
}
