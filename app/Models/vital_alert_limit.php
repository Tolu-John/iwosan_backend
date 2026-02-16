<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class vital_alert_limit extends Model
{
    use HasFactory;

    protected $fillable = [
        'ward_id',
        'name',
        'low_limit',
        'high_limit',
    ];
}
