<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;


    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    
    public function carer()
    {
        return $this->belongsTo(Carer::class);
    }

    public function consultation(){

        return $this->belongsTo(Consultation::class);

    }


    protected $fillable=[
        'text',
        'rating',
        'recomm',
        'tags',
        'status',
        'response_text',
        'response_at',
        'response_by',
        'edited_at',
        'deleted_reason'
    ];

    protected $casts = [
        'response_at' => 'datetime',
        'edited_at' => 'datetime',
    ];
}
