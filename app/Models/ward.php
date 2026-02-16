<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ward extends Model
{
    use HasFactory;

    public function ward_note()
    {
        return $this->hasMany(ward_note::class);
    }

    public function ward_temp()
    {
        return $this->hasMany(ward_temp::class);
    }

    public function ward_bp()
    {
        return $this->hasMany(ward_bp::class);
    }

    public function ward_sugar()
    {
        return $this->hasMany(ward_sugar::class);
    }


    public function ward_weight()
    {
        return $this->hasMany(ward_weight::class);
    }

    public function drug()
    {
        return $this->hasMany(Drug::class);
    }

    public function lab_test()
    {
        return $this->hasMany(lab_test::class);
    }

    public function vital_alert_limit()
    {
        return $this->hasMany(vital_alert_limit::class);
    }

    public function timeline()
    {
        return $this->hasMany(timeline::class);
    }

    public function other_vitals()
    {
        return $this->hasMany(other_vitals::class);
    }


    public function appointment()
    {
        return $this->hasOne(Appointment::class);
    }



    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function carer()
    {
        return $this->belongsTo(Carer::class);
    }

    public function hospital()
    {
        return $this->belongsTo(Hospital::class);
    }


    protected $fillable=[
        'diagnosis',
        'admission_date',
        'ward_vitals',
        'discharged',
        'discharge_date',
        'discharge_summary',
        'priority',

    ];
}
