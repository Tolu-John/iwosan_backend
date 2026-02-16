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
        'date_time',
        'address',
        'admin_approved'

    ];

    protected $casts = [
        'address' => EncryptedString::class,
    ];

}
