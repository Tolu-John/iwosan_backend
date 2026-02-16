<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class timeline extends Model
{
    use HasFactory;


    public function ward(){

        return $this->belongsTo(ward::class);
    }


    protected $fillable=[
        'text', 
        'type',  
        'type_id',
        'author_id',
        'author_role',
        'meta',
    ];
}
