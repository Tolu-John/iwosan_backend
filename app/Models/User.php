<?php

namespace App\Models;

use App\Casts\EncryptedString;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;



    public function carer()
    {
        return $this->hasOne(Carer::class);
    }

    public function patient()
    {
        return $this->hasOne(Patient::class);
    }

    public function hospital()
    {
        return $this->hasOne(Hospital::class, 'user_id');
    }


    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'firstname',
        'lastname',
        'email',
        'password',
        'user_img',
        'phone',
        'age',
        'gender',
        'firedb_id',
        'address',
        'lat',
        'lon',
  
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'address' => EncryptedString::class,
    ];
}
