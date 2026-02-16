<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class test extends Model
{
    use HasFactory;

public function hospital(){

    return $this->belongsTo(Hospital::class);
}

protected $fillable=[
    'name',
    'code',
    'sample_type',
    'turnaround_time',
    'preparation_notes',
    'is_active',
    'status_reason',
    'price',
    'extra_notes'
];

public function priceHistories()
{
    return $this->hasMany(TestPriceHistory::class, 'test_id');
}



}
