<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VConsultationResource extends JsonResource
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
                'consult_type'=>$this->consult_type,
                'duration'=>$this->duration,
                'created_at'=>$this->created_at,
                'updated_at'=>$this->updated_at
    ];
    }
}
