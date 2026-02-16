<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommThread extends Model
{
    use HasFactory;

    protected $fillable = [
        'consultation_id',
        'channel',
        'provider_thread_id',
        'status',
        'last_message_at',
        'created_by_user_id',
        'created_by_role',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function consultation()
    {
        return $this->belongsTo(Consultation::class);
    }

    public function participants()
    {
        return $this->hasMany(CommParticipant::class, 'thread_id');
    }

    public function events()
    {
        return $this->hasMany(CommEvent::class, 'thread_id');
    }
}
