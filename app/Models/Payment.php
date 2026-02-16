<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;



    public function transfer()
    {
        return $this->hasOne(Transfers::class, 'payment_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function carer()
    {
        return $this->belongsTo(Carer::class);
    }

    public function consultation()
    {
        return $this->belongsTo(Consultation::class);
    }

    public function teletest(){

        return $this->belongsTo(Teletest::class);
    }













protected $fillable=[

    'status',
    'status_reason',
    'code',
    'reference',
    'gateway',
    'verified_at',
    'gateway_transaction_id',
    'channel',
    'currency',
    'fees',
    'gateway_payload',
    'processing_at',
    'paid_at',
    'failed_at',
    'refunded_at',
    'price',
    'method',
    'reuse',
    'type',
    'type_id',


];

protected $casts = [
    'verified_at' => 'datetime',
    'processing_at' => 'datetime',
    'paid_at' => 'datetime',
    'failed_at' => 'datetime',
    'refunded_at' => 'datetime',
    'gateway_payload' => 'array',
    'reuse' => 'boolean',
];

}
