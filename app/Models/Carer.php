<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Carer extends Model
{
    use HasFactory;



    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function hospital()
    {
        return $this->belongsTo(Hospital::class);
    }
   
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function consultations()
    {
        return $this->hasMany(Consultation::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }


    public function teletests(){

        return $this->hasMany(Teletest::class);

    }

    public function transfers()
    {
        return $this->hasMany(Transfers::class);
    }



    protected $fillable = [
        'position',
        'rating',
        'bio',
        'onHome_leave',
        'onVirtual_leave',
        'admin_approved',
        'qualifications',
        'virtual_day_time',
        'home_day_time',
        'super_admin_approved',
        'service_radius_km',
        'response_time_minutes'
    ];

   

}
