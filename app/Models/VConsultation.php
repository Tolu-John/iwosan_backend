<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VConsultation extends Model
{
    use HasFactory;


public function consultation(){

return $this->belongsTo(Consultation::class);

}



    protected $fillable=[

        'consult_type',
        'duration'




    ];


}
