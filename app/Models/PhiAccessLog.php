<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhiAccessLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'role',
        'route',
        'method',
        'target_type',
        'target_id',
        'accessed_at',
        'metadata',
    ];

    protected $casts = [
        'accessed_at' => 'datetime',
        'metadata' => 'array',
    ];
}
