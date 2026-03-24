<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EpisodeBillingCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'cycle_start',
        'cycle_end',
        'due_at',
        'amount_due_minor',
        'amount_paid_minor',
        'billing_status',
        'last_reminder_at',
        'grace_until',
    ];

    protected $casts = [
        'cycle_start' => 'date',
        'cycle_end' => 'date',
        'due_at' => 'datetime',
        'last_reminder_at' => 'datetime',
        'grace_until' => 'datetime',
    ];
}
