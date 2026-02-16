<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplaintAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'complaint_id',
        'action',
        'changes',
        'created_by',
        'created_role',
    ];

    protected $casts = [
        'changes' => 'array',
    ];
}
