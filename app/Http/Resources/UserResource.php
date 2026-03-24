<?php

namespace App\Http\Resources;

use Carbon\Carbon;
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
        // Legacy field naming: "age" stores DOB in many records.
        $dateOfBirth = is_null($this->age) ? null : trim((string) $this->age);
        $computedAge = 0;
        if (!empty($dateOfBirth) && strtolower($dateOfBirth) !== 'null' && $dateOfBirth !== '0') {
            try {
                $dob = Carbon::parse($dateOfBirth);
                $computedAge = $dob->isFuture() ? 0 : $dob->age;
            } catch (\Throwable $e) {
                $computedAge = 0;
            }
        }

        return [
            'id'=>(string)$this->id,
                'firstname'=> $this->firstname,
                'lastname'=>$this->lastname,
                'email'=>$this->email,
                'user_img'=>$this->user_img,
                'phone'=>$this->phone,
                'dob'=>$this->age,
                'age'=>(string)$computedAge,
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
