<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetricDailySummary extends Model
{
    protected $fillable = [
        'date',
        'actor_role',
        'owner_type',
        'owner_id',
        'conversion_rate',
        'completion_rate',
        'refund_rate',
        'counts',
    ];

    protected $casts = [
        'date' => 'date',
        'counts' => 'array',
    ];
}
