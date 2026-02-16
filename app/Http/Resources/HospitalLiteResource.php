<?php

namespace App\Http\Resources;

use App\Models\Carer;
use App\Models\Review;
use Illuminate\Http\Resources\Json\JsonResource;

class HospitalLiteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {

        $carerIds = Carer::where('hospital_id', $this->id)->pluck('id');
        $rating = 0.0;
        if ($carerIds->isNotEmpty()) {
            $rating = (float) Review::whereIn('carer_id', $carerIds)->avg('rating');
        }


        return [
            'id'=>(string)$this->id,
                'name'=>$this->name,
                'firedb_id'=>$this->firedb_id,
                'rating'=> number_format($rating, 1, '.', ''),
                'phone'=>$this->phone,
                'hospital_img'=>$this->hospital_img,
                'email'=>$this->email,
                'verified' => (bool) $this->super_admin_approved,
                'lat'=>$this->lat,
                'lon'=>$this->lon,
                'address'=>$this->address,
                'home_visit_price'=>$this->home_visit_price,
                'virtual_visit_price'=>$this->virtual_visit_price,
                'virtual_ward_price'=>$this->virtual_ward_price,
            ];

    }
}
