<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FileAccessLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_type',
        'path',
        'owner_type',
        'owner_id',
        'user_id',
        'ip_address',
        'user_agent',
    ];
}
