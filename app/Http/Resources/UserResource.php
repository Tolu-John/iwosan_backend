<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {

        //age referred to here is dob
        $dateOfBirth = $this->age;
        $today = date("d-m-Y");
         $diff = date_diff(date_create($dateOfBirth), date_create($today));

        return [
            'id'=>(string)$this->id,
                'firstname'=> $this->firstname,
                'lastname'=>$this->lastname,
                'email'=>$this->email,
                'user_img'=>$this->user_img,
                'phone'=>$this->phone,
                'dob'=>$this->age,
                'age'=>$diff->format('%y'),
                'gender'=>$this->gender,
                'address'=>$this->address,
                'lat'=>$this->lat,
                'lon'=>$this->lon,
                'firedb_id'=>$this->firedb_id,
                'created_at'=>$this->created_at,
                'updated_at'=>$this->updated_at
        ];
    }
}
