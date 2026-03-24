<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'appointment_status_history';

    protected $fillable = [
        'appointment_id',
        'from_status',
        'to_status',
        'action_key',
        'actor_role',
        'actor_id',
        'reason_code',
        'reason_note',
        'metadata_json',
    ];

    protected $casts = [
        'metadata_json' => 'array',
    ];
}
