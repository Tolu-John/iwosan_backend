<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestPriceHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_id',
        'hospital_id',
        'previous_price',
        'price',
        'changed_by',
        'reason',
    ];

    public function test()
    {
        return $this->belongsTo(test::class, 'test_id');
    }
}
