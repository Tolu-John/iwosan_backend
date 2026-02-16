<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SecurityIncident extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'severity',
        'status',
        'route',
        'ip',
        'metadata',
        'response_notes',
        'assigned_to',
        'detected_at',
        'resolved_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
