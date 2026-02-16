<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabTest extends Model
{
    use HasFactory;

    public function consultation(){

    return $this->belongsTo(Consultation::class);

    }

    public function ward(){

        return $this->belongsTo(ward::class);
    }


    protected $fillable=[
        'done',
        'test_name',
        'lab_recomm',
        'extra_notes',
        'status',
        'status_reason',
        'scheduled_at',
        'collected_at',
        'resulted_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'collected_at' => 'datetime',
        'resulted_at' => 'datetime',
        'done' => 'boolean',
    ];
}
