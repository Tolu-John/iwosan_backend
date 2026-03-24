<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomeAdmissionQuoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quote_id',
        'item_type',
        'item_code',
        'label',
        'qty',
        'unit_price_minor',
        'line_total_minor',
    ];
}
