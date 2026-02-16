<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Consultation extends Model
{
    use HasFactory;

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function carer()
    {
        return $this->belongsTo(Carer::class);
    }

    public function payment(){

        return $this->hasOne(Payment::class);
    }

    public function hospital(){

        return $this->hasOne(Hospital::class);
    }

    public function review(){

        return $this->hasOne(Review::class);
    }

    public function drugs(){

        return $this->hasMany(Drug::class);
    }


    public function labtests(){

        return $this->hasMany(LabTest::class);
    }

    public function sd(){

        return $this->hasMany(SD::class);
    }

    public function vconsultation(){
  
     return $this->hasOne(VConsultation::class);

    }

    public function hconsultation(){
  
        return $this->hasOne(HConsultation::class);
   
       }


    protected $fillable=[

        'treatment_type',
        'diagnosis',
        'status',
        'consult_notes',
        'date_time',










    ];
}
