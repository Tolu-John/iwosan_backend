<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabResult extends Model
{
    use HasFactory;
    use SoftDeletes;


    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    



  protected $fillable=[

    'name',
    'lab_name',
    'result_picture',
    'result_picture_front',
    'result_picture_back',
    'extra_notes',
    'uploaded_at',
    'uploaded_by',
    'uploaded_role',
    'source',



  ];

  protected $casts = [
    'uploaded_at' => 'datetime',
  ];





}
