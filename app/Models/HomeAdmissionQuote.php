<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomeAdmissionQuote extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'version',
        'currency',
        'enrollment_fee_minor',
        'recurring_fee_minor',
        'billing_cycle',
        'addons_total_minor',
        'discount_total_minor',
        'tax_total_minor',
        'grand_total_minor',
        'quote_status',
        'valid_until',
        'approved_by',
        'approved_at',
        'metadata_json',
    ];

    protected $casts = [
        'valid_until' => 'datetime',
        'approved_at' => 'datetime',
        'metadata_json' => 'array',
    ];
}
