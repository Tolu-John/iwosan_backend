<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Complaints extends Model
{
    use HasFactory;

    public function hospital(){

        return $this->belongsTo(Hospital::class);
    }
    
    public function patient(){

        return $this->belongsTo(Patient::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    protected $fillable=[
        'title',
        'complaint',
        'category',
        'severity',
        'status',
        'resolution_notes',
        'resolved_at',
        'closed_at',
        'rejected_at',
        'rejection_reason',
        'assigned_to',
        'first_response_at',
        'response_notes',
    ];

    protected $casts = [
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];
}
