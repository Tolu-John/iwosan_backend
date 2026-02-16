<?php

namespace App\Http\Resources;

use App\Models\Hospital;
use Illuminate\Http\Resources\Json\JsonResource;

class TestResource extends JsonResource
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
                'hospital_id'=>$this->hospital_id,
                'hospital'=>new HospitalLiteResource(Hospital::find($this->hospital_id)),
                'name'=>$this->name,
                'code'=>$this->code,
                'sample_type'=>$this->sample_type,
                'turnaround_time'=>$this->turnaround_time,
                'preparation_notes'=>$this->preparation_notes,
                'is_active'=>(bool) $this->is_active,
                'status_reason'=>$this->status_reason,
                'extra_notes'=>$this->extra_notes,
                'price'=>$this->price,
               
        ];
    }
}
