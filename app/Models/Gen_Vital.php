<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gen_Vital extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'gen_vitals';

public function patients(){

        return $this->belongsTo(Patient::class);
    }


    protected $fillable=[
        'name',
        'type',
        'value',
        'unit',
        'value_num',
        'systolic',
        'diastolic',
        'pulse',
        'taken_at',
        'recorded_at',
        'context',
        'source',
        'device_name',
        'device_model',
        'device_serial',
        'location',
        'notes',
        'status_flag',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
        'recorded_at' => 'datetime',
        'value_num' => 'decimal:2',
        'systolic' => 'decimal:2',
        'diastolic' => 'decimal:2',
        'pulse' => 'decimal:2',
    ];

}
