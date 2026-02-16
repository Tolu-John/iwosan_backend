<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisitLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'consultation_id',
        'lat',
        'lng',
        'address',
        'source',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
