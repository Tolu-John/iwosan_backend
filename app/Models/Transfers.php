<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transfers extends Model
{
    use HasFactory;


    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function hospital()
    {
        return $this->belongsTo(Hospital::class);
    }

    public function carer()
    {
        return $this->belongsTo(Carer::class);
    }



    protected $fillable = [
        'recipient',
        'amount',
        'reason',
        'status',
        'reference',
        'currency',
        'method',
        'type',
        'requested_at',
        'processed_at',
        'paid_at',
        'failed_at',
        'failure_reason',
        'requested_by',
        'requested_role',
    ];

}
