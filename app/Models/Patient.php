<?php

namespace App\Models;

use App\Casts\EncryptedString;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }


    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    
    public function consultations()
    {
        return $this->hasMany(Consultation::class);
    }


    public function labresults()
    {
        return $this->hasMany(LabResult::class);
    }

    public function teletests(){

        return $this->hasMany(Teletest::class);

    }


    public function complaints(){

        return $this->hasMany(Complaint::class);

    }







    protected $fillable = [
        'bloodtype',
        'genotype',
        'temperature',
        'sugar_level',
        'bloodpressure',
        'bp_dia',
        'bp_sys',
        'weight',
        'height',

        'kin_name',
        'kin_phone',
        'kin_address',
        
        'other_kin_name',
        'other_kin_phone',
        'other_kin_address',
    ];

    protected $casts = [
        'kin_address' => EncryptedString::class,
        'other_kin_address' => EncryptedString::class,
    ];

}
