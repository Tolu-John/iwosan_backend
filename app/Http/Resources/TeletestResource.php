<?php

namespace App\Http\Resources;

use App\Models\Carer;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class TeletestResource extends JsonResource
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
                'patient'=> new PatientLiteResource(Patient::find($this->patient_id)),
                'carer_id'=>$this->carer_id,
                'carer'=>new CarerLiteResource(Carer::find($this->carer_id)),
                'review_id'=>$this->review_id,
                'hospital_id'=>$this->hospital_id,
                'review'=> new ReviewResource(Review::find($this->review_id)),
                'payment_id'=>$this->payment_id,
                'payment'=> new PaymentResource(Payment::find($this->payment_id)),
                'test_name'=>$this->test_name,
                'status'=>$this->status,
                'status_description'=>$this->status_description,
                'scheduled_at'=>$this->scheduled_at,
                'started_at'=>$this->started_at,
                'completed_at'=>$this->completed_at,
                'cancelled_at'=>$this->cancelled_at,
                'no_show_at'=>$this->no_show_at,
                'address'=>$this->address,
                 'date_time'=>$this->date_time,
                'admin_approved'=>$this->admin_approved,
                'updated_at'=>$this->updated_at,
            ];
    }
}
