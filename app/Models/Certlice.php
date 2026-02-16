<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certlice extends Model
{
    use HasFactory;

            protected $fillable=[
                'type_id',
                'type',
                'location',
                'file_name',
                'cert_type',
                'issuer',
                'license_number',
                'status',
                'issued_at',
                'expires_at',
                'verified_at',
                'verified_by',
                'notes'
            ];

}
