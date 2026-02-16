<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ward_temp extends Model
{
    use HasFactory;

    public function ward(){

        return $this->belongsTo(ward::class);
    }


    protected $fillable=[
        'value',
        'taken_at',
        'recorded_at',
        'source',
    ];
}
