<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HospitalPriceHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'hospital_id',
        'previous_home_visit_price',
        'previous_virtual_visit_price',
        'previous_virtual_ward_price',
        'home_visit_price',
        'virtual_visit_price',
        'virtual_ward_price',
        'changed_by',
        'reason',
        'ip_address',
        'user_agent',
    ];

    public function hospital()
    {
        return $this->belongsTo(Hospital::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
