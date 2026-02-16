<?php

namespace App\Http\Resources;

use App\Models\Carer;
use App\Models\Hospital;
use App\Models\Payment;
use Illuminate\Http\Resources\Json\JsonResource;

class TransferResource extends JsonResource
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
                'payment_id'=>$this->payment_id,
                'payment'=> new PaymentResource(Payment::find($this->payment_id)),
                'type_id'=>$this->type_id,
                'type'=>$this->type,
                'hospital'=> new HospitalLiteResource(Hospital::find($this->hospital_id)),
                'hospital_id'=>$this->hospital_id,
                'carer'=> new CarerLiteResource(Carer::find($this->carer_id)),
                'carer_id'=>$this->carer_id,
                'recipient'=>$this->recipient,
                'amount'=>$this->amount,
                'currency'=>$this->currency,
                'method'=>$this->method,
                'reason'=>$this->reason,
                'status'=>$this->status,
                'reference'=>$this->reference,
                'requested_at'=>$this->requested_at,
                'processed_at'=>$this->processed_at,
                'paid_at'=>$this->paid_at,
                'failed_at'=>$this->failed_at,
                'failure_reason'=>$this->failure_reason,
                'created_at'=>$this->created_at,
                'updated_at'=>$this->updated_at,
            ];
    }
}
