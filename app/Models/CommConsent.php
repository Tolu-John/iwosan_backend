<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommConsent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role',
        'channel',
        'scope',
        'version',
        'consultation_id',
        'consented_at',
    ];

    protected $casts = [
        'consented_at' => 'datetime',
    ];
}
