<?php

namespace App\Http\Resources;

use App\Models\Carer;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use App\Services\AccessService;
use App\Services\TeletestWorkflowService;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;

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
        $workflowPayload = null;
        try {
            /** @var TeletestWorkflowService $workflow */
            $workflow = app(TeletestWorkflowService::class);
            /** @var AccessService $access */
            $access = app(AccessService::class);
            $workflowPayload = $workflow->buildStatePayload($this->resource, $access);
        } catch (Throwable $e) {
            $workflowPayload = null;
        }

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
                'status_reason'=>$this->status_reason,
                'status_reason_note'=>$this->status_reason_note,
                'scheduled_at'=>$this->scheduled_at,
                'departed_at'=>$this->departed_at,
                'arrived_at'=>$this->arrived_at,
                'started_at'=>$this->started_at,
                'completed_at'=>$this->completed_at,
                'cancelled_at'=>$this->cancelled_at,
                'no_show_at'=>$this->no_show_at,
                'reassigned_at'=>$this->reassigned_at,
                'reassigned_from'=>$this->reassigned_from,
                'reassigned_to'=>$this->reassigned_to,
                'current_eta_minutes'=>$this->current_eta_minutes,
                'eta_last_updated_at'=>$this->eta_last_updated_at,
                'allowed_actions'=>$workflowPayload['allowed_actions'] ?? [],
                'status_timestamps'=>$workflowPayload['status_timestamps'] ?? [
                    'scheduled_at' => $this->scheduled_at,
                    'departed_at' => $this->departed_at,
                    'arrived_at' => $this->arrived_at,
                    'started_at' => $this->started_at,
                    'completed_at' => $this->completed_at,
                    'cancelled_at' => $this->cancelled_at,
                    'no_show_at' => $this->no_show_at,
                ],
                'eta'=>$workflowPayload['eta'] ?? [
                    'minutes' => $this->current_eta_minutes,
                    'last_updated_at' => $this->eta_last_updated_at,
                ],
                'ux_hints'=>$workflowPayload['ux_hints'] ?? [
                    'primary_cta' => null,
                    'supporting_copy' => null,
                ],
                'address'=>$this->address,
                 'date_time'=>$this->date_time,
                'admin_approved'=>$this->admin_approved,
                'updated_at'=>$this->updated_at,
            ];
    }
}
