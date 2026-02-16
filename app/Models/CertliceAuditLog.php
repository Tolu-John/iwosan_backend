<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CertliceAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'certlice_id',
        'actor_id',
        'actor_role',
        'action',
        'from_status',
        'to_status',
        'reason',
    ];
}
