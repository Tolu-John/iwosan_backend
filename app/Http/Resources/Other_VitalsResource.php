<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Other_VitalsResource extends JsonResource
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
                'ward_id'=>$this->ward_id,
               'value'=>$this->value,
               'name'=>$this->name,
               'unit'=>$this->unit,
               'taken_at'=>$this->taken_at,
               'recorded_at'=>$this->recorded_at,
               'source'=>$this->source,
                'created_at'=>$this->created_at,
                'updated_at'=>$this->updated_at
            
            ];
    }
}
