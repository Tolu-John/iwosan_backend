<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class other_vitals extends Model
{
    use HasFactory;
    
    public function ward(){

        return $this->belongsTo(ward::class);
    }


    protected $fillable=[
        'name', 
        'value',
        'unit',
        'taken_at',
        'recorded_at',
        'source',
    ];



}
