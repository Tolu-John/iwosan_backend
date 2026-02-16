<?php

namespace App\Http\Resources;

use App\Models\Carer;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\Teletest;
use Illuminate\Http\Resources\Json\JsonResource;


class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {

  

        return[ 
        'id'=>(string)$this->id,
            'patient_id'=>$this->patient_id,
            'patient'=> new PatientLiteResource(Patient::find($this->patient_id)),
            'carer'=>new CarerLiteResource(Carer::find($this->carer_id)),
            'carer_id'=>$this->carer_id,
            'price'=>$this->price,
            'type'=>$this->type,
            'type_id'=>$this->type_id,
            'status'=>$this->status,
            'status_reason'=>$this->status_reason,
            'code'=>$this->code,
            'reference'=>$this->reference,
            'gateway'=>$this->gateway,
            'gateway_transaction_id' => $this->gateway_transaction_id,
            'channel' => $this->channel,
            'currency' => $this->currency,
            'fees' => $this->fees,
            'verified_at'=>$this->verified_at,
            'processing_at' => $this->processing_at,
            'paid_at' => $this->paid_at,
            'failed_at' => $this->failed_at,
            'refunded_at' => $this->refunded_at,
            'updated_at'=>$this->updated_at,
            'method'=>$this->method,
            'reuse'=>$this->reuse,
        ];
    }
}
