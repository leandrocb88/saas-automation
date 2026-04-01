<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class DatasetVideo extends Pivot
{
    protected $table = 'dataset_videos';

    protected $fillable = [
        'dataset_id',
        'video_id',
    ];
}
