<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ward_note extends Model
{
    use HasFactory;

    public function ward(){

        return $this->belongsTo(ward::class);
    }


    protected $fillable=[
        'text',
        'note_type',
        'author_id',
        'author_role',
        'recorded_at',
    ];
}
