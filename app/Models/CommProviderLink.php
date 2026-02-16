<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommProviderLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'thread_id',
        'provider',
        'wa_id',
        'phone',
        'external_id',
    ];

    public function thread()
    {
        return $this->belongsTo(CommThread::class, 'thread_id');
    }
}
