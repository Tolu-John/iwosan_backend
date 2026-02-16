<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VitalAuditLog extends Model
{
    protected $fillable = [
        'vital_id',
        'patient_id',
        'actor_id',
        'actor_type',
        'action',
        'snapshot',
    ];
}
