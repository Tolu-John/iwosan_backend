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
    'category',
    'sample_type',
    'turnaround_time',
    'preparation_notes',
    'fasting_required',
    'is_active',
    'status_reason',
    'price',
    'cash_price',
    'hmo_price',
    'emergency_price',
    'extra_notes'
];

protected $casts = [
    'fasting_required' => 'boolean',
    'is_active' => 'boolean',
    'price' => 'integer',
    'cash_price' => 'integer',
    'hmo_price' => 'integer',
    'emergency_price' => 'integer',
];

public function priceHistories()
{
    return $this->hasMany(TestPriceHistory::class, 'test_id');
}



}
