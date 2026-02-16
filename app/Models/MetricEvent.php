<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetricEvent extends Model
{
    protected $fillable = [
        'event_type',
        'model_type',
        'model_id',
        'actor_id',
        'actor_role',
        'owner_type',
        'owner_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
