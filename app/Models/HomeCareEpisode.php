<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomeCareEpisode extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'patient_id',
        'hospital_id',
        'episode_status',
        'current_quote_id',
        'care_plan_json',
        'started_at',
        'paused_at',
        'closed_at',
        'discharged_at',
    ];

    protected $casts = [
        'care_plan_json' => 'array',
        'started_at' => 'datetime',
        'paused_at' => 'datetime',
        'closed_at' => 'datetime',
        'discharged_at' => 'datetime',
    ];
}
