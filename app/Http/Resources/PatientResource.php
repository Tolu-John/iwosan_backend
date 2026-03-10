<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\User;

class PatientResource extends JsonResource
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
                'user'=> new UserResource(User::find($this->user_id)),
                 'user_id'=>$this->user_id,
                'bloodtype'=>$this->bloodtype,
                'genotype'=>$this->genotype,
                'temperature'=>$this->temperature,
                'call_address'=>$this->call_address,
                'sugar_level'=>(string)$this->sugar_level,
                'bloodpressure'=>(string)$this->bloodpressure,
                'bp_dia'=>(string)$this->bp_dia,
                'bp_sys'=>(string)$this->bp_sys,
                'weight'=>(string)$this->weight,
                'height'=>(string)$this->height,
                'created_at'=>$this->created_at,
                'updated_at'=>$this->updated_at,

                'kin_name'=>$this->kin_name,
                'kin_phone'=>$this->kin_phone,
                'kin_address'=>$this->kin_address,

                'other_kin_name'=>$this->other_kin_name,
                'other_kin_phone'=>$this->other_kin_phone,
                'other_kin_address'=>$this->other_kin_address,
                'push_notifications_enabled' => (bool) $this->push_notifications_enabled,
                'sms_alerts_enabled' => (bool) $this->sms_alerts_enabled,
                'share_vitals_with_carers' => (bool) $this->share_vitals_with_carers,
    ];
    }
}
