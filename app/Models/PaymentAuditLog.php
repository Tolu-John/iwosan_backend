<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'from_status',
        'to_status',
        'source',
        'reason',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
