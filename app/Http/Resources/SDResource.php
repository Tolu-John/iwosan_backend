<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SDResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return  [
            "id" =>(string)$this->id,
            'type'=>'SD',
        'attributes'=>[
            'consultation_id'=>(string)$this->consultation_id,
            "structure_type" => $this->structure_type,
            "location_x" => $this->location_x,
            "location_y" => $this->location_y,
            "feeling" => $this->feeling,
            "duration" => $this->duration,
            "note" => $this->note,
            "front_back" => $this->front_back,
            "number" => $this->number,
          ]
            ];
    }
}
