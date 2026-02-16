<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusChangeLog extends Model
{
    protected $fillable = [
        'model_type',
        'model_id',
        'from_status',
        'to_status',
        'actor_id',
        'actor_type',
        'reason',
    ];
}
