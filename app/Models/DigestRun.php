<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DigestRun extends Model
{
    protected $fillable = [
        'user_id',
        'digest_id',
        'batch_id',
        'summary_count',
        'total_duration',
        'pdf_path',
        'audio_path',
        'pdf_status',
        'audio_status',
        'audio_duration',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function digest()
    {
        return $this->belongsTo(Digest::class);
    }

    public function videos()
    {
        return $this->hasMany(Video::class, 'share_token', 'batch_id');
    }
}
