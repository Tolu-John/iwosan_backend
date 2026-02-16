<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'thread_id',
        'direction',
        'event_type',
        'provider_message_id',
        'sender_role',
        'event_timestamp',
        'delivery_status',
        'signature_status',
        'metadata',
    ];

    protected $casts = [
        'event_timestamp' => 'datetime',
        'metadata' => 'array',
    ];

    public function thread()
    {
        return $this->belongsTo(CommThread::class, 'thread_id');
    }
}
