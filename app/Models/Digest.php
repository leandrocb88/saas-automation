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
        'mode',
        'search_term',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function channels()
    {
        return $this->belongsToMany(Channel::class, 'digest_channels');
    }
}
