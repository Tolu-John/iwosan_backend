<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EscalationEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'escalation_id',
        'event_type',
        'actor_role',
        'actor_id',
        'payload_json',
    ];

    protected $casts = [
        'payload_json' => 'array',
    ];
}
