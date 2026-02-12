<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DigestSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'preferred_time',
        'timezone',
        'is_active',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
