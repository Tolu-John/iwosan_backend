<?php

namespace App\Models;


use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;



class Hospital extends Model
{
    use HasApiTokens, HasFactory, Notifiable;



    public function carer()
    {
        return $this->hasMany(Carer::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function teletests()
    {
        return $this->hasMany(Teletest::class);
    }

    public function consultation(){

        return $this->hasMany(Consultation::class);
    }

    public function transfers()
    {
        return $this->hasMany(Transfers::class);
    }

    public function tests()
    {
        return $this->hasMany(Test::class);
    }
    
    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }

    public function priceHistories()
    {
        return $this->hasMany(HospitalPriceHistory::class);
    }

    public function Pharmdrug()
    {
        return $this->belongsToMany(Pharmdrug::class, 'Pharmdrug_hospital', 'hospital_id', 'Pharmdrug_id');
    }
    

    protected $fillable = [
        'name',
        'code',
        'rating',
        'about_us',
        'website',
        'hospital_img',
        'email',
        'lat',
        'lon',
        'account_number',
        'account_name',
        'bank_name',
        'bank_code',
      'home_visit_price',
    'virtual_visit_price',
        'virtual_ward_price',
        'recipient',
        'phone',
        'password',
        'address',
        'super_admin_approved',
        'firedb_id'
    
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
    ];

}
