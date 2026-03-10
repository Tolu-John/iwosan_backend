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
        'address_lat',
        'address_lon',
        'price',
        'appointment_type',
        'admin_approved',
        'date_time',
        'owned_by_role',
        'owned_by_id',
        'next_action_at',
        'channel',
        'extra_notes',
        'consent_accepted',
        'attachments_json',

    ];

    protected $casts = [
        'address' => EncryptedString::class,
        'consent_accepted' => 'boolean',
        'attachments_json' => 'array',
        'next_action_at' => 'datetime',
    ];
}
