<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarerApprovalLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'carer_id',
        'hospital_id',
        'status',
        'reason',
        'reviewed_by',
    ];

    public function carer()
    {
        return $this->belongsTo(Carer::class);
    }

    public function hospital()
    {
        return $this->belongsTo(Hospital::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
