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
        'approved_at',
        'departed_at',
        'arrived_at',
        'assignment_source',
        'current_eta_minutes',
        'channel',
        'extra_notes',
        'consent_accepted',
        'attachments_json',
        'dispatch_model',
        'address_source',
        'contact_profile',
        'visit_reason',
        'preferred_window',
        'home_window_code',
        'home_window_label',
        'expected_duration',
        'red_flags_json',
        'preferred_hospital_id',
        'preferred_hospital_name',
        'preferred_clinician_id',
        'preferred_clinician_name',
        'preference_note',
        'additional_notes',
        'status_reason_code',
        'status_reason_note',
        'visit_contact_name',
        'visit_contact_phone',

    ];

    protected $casts = [
        'address' => EncryptedString::class,
        'consent_accepted' => 'boolean',
        'attachments_json' => 'array',
        'next_action_at' => 'datetime',
        'approved_at' => 'datetime',
        'departed_at' => 'datetime',
        'arrived_at' => 'datetime',
        'red_flags_json' => 'array',
    ];
}
