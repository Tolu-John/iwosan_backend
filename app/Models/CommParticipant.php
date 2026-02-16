<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'thread_id',
        'user_id',
        'role',
        'wa_id',
        'phone',
    ];

    public function thread()
    {
        return $this->belongsTo(CommThread::class, 'thread_id');
    }
}
