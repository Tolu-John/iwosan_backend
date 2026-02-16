<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class HConsultationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id'=>(string)$this->id,
                'consultation_id'=>$this->consultation_id,
                'ward_id'=>$this->ward_id,
                'address'=>$this->address,
                'admitted'=>$this->admitted,
                'start_date'=>$this->created_at,
                'end_date'=>$this->updated_at
    ];
    }
}
