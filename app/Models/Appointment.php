<?php

namespace App\Models;

use App\Casts\EncryptedString;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    public function transfers()
    {
        return $this->hasOne(Transfers::class);
    }

    public function payments()
    {
        return $this->hasOne(Payment::class);
    }

    public function ward()
    {
        return $this->hasOne(ward::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function carer()
    {
        return $this->belongsTo(Carer::class);
    }


    protected $fillable=[
        'status',
        'consult_type',
        'address',
        'price',
        'appointment_type',
        'admin_approved',
        'date_time',
        'channel',
        'extra_notes'

    ];

    protected $casts = [
        'address' => EncryptedString::class,
    ];
}
