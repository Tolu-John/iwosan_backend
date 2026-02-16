<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $fillable = [
        'key',
        'user_id',
        'method',
        'path',
        'request_hash',
        'response_status',
        'response_body',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];
}
