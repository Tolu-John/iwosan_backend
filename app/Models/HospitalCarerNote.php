<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HospitalCarerNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'hospital_id',
        'carer_id',
        'created_by_user_id',
        'note',
    ];

    public function hospital()
    {
        return $this->belongsTo(Hospital::class);
    }

    public function carer()
    {
        return $this->belongsTo(Carer::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
