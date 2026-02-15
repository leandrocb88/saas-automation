<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'url',
        'youtube_channel_id',
        'name',
        'thumbnail_url',
        'is_paused',
        'subscriber_count',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function videos()
    {
        return $this->hasMany(Video::class);
    }

    public function digests()
    {
        return $this->belongstoMany(Digest::class, 'digest_channels');
    }
}
