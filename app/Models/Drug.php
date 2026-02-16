<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Drug extends Model
{
    use HasFactory;


    public function consultation(){

        return $this->belongsTo(Consultation::class);
    }


    public function ward(){

        return $this->belongsTo(ward::class);
    }





    protected $fillable=[
        'name',
        'stop_date',
        'start_date',
        'drug_type',
        'status',
        'status_reason',
        'dosage',
        'extra_notes',
        'duration',
        'quantity',
        'started',
        'finished',
          'carer_name'
     

    ];
}
