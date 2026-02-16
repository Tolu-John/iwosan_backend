<?php

namespace App\Models;

use App\Casts\EncryptedString;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HConsultation extends Model
{
    use HasFactory;
    

    public function consultation(){

        return $this->belongsTo(consultation::class);
    }

    public function ward(){

        return $this->belongsTo(ward::class);
    }



    protected $fillable= [
        'address',
        'admitted'
    ];

    protected $casts = [
        'address' => EncryptedString::class,
    ];
   
}
