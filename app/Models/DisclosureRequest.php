<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DisclosureRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'requested_by',
        'role',
        'scope',
        'resource',
        'filters',
        'justification',
        'status',
        'review_notes',
        'approved_by',
        'requested_at',
        'approved_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
    ];
}
