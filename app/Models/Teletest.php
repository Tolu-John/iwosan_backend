<?php

namespace App\Models;

use App\Casts\EncryptedString;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Teletest extends Model
{
    use HasFactory;

    


    public function patient(){

        return $this->belongsTo(Patient::class);
    }

    public function carer(){

        return $this->belongsTo(Carer::class);
    }


    public function hospital(){

        return $this->belongsTo(Hospital::class);
    }


    public function payment(){

        return $this->hasOne(Payment::class);
    }

    protected $fillable=[
        'test_name',
        'status',
        'status_description',
        'status_reason',
        'status_reason_note',
        'date_time',
        'address',
        'admin_approved',
        'scheduled_at',
        'departed_at',
        'arrived_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'no_show_at',
        'reassigned_at',
        'reassigned_from',
        'reassigned_to',
        'current_eta_minutes',
        'eta_last_updated_at',

    ];

    protected $casts = [
        'address' => EncryptedString::class,
        'scheduled_at' => 'datetime',
        'departed_at' => 'datetime',
        'arrived_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'no_show_at' => 'datetime',
        'reassigned_at' => 'datetime',
        'eta_last_updated_at' => 'datetime',
    ];

}
