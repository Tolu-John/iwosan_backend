<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Gen_VitalResource extends JsonResource
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
            'patient_id'=>$this->patient_id,
           'type'=>$this->type ?? $this->name,
           'name'=>$this->name,
           'value'=>$this->value,
           'value_num'=>$this->value_num,
           'unit'=>$this->unit,
           'systolic'=>$this->systolic,
           'diastolic'=>$this->diastolic,
           'pulse'=>$this->pulse,
           'taken_at'=>$this->taken_at,
           'recorded_at'=>$this->recorded_at,
           'context'=>$this->context,
           'source'=>$this->source,
           'device_name'=>$this->device_name,
           'device_model'=>$this->device_model,
           'device_serial'=>$this->device_serial,
           'location'=>$this->location,
           'notes'=>$this->notes,
           'status_flag'=>$this->status_flag,
            'created_at'=>$this->created_at,
            'updated_at'=>$this->updated_at
        
        ];


        
    }
}
