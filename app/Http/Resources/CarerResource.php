<?php

namespace App\Http\Resources;

use App\Models\Hospital;
use App\Models\Teletest;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Consultation;
use App\Models\User;
use App\Models\Appointment;
use App\Models\CarerApprovalLog;
use Illuminate\Http\Resources\Json\JsonResource;

class CarerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
      $isApproved = (int) $this->admin_approved === 1 && (int) $this->super_admin_approved === 1;
      $latestApproval = CarerApprovalLog::where('carer_id', $this->id)
            ->orderByDesc('id')
            ->first();
      $latestApprovalStatus = $latestApproval?->status;
      $rejectionReason = $latestApprovalStatus === 'rejected'
            ? ($latestApproval?->reason ?? null)
            : null;
      $isReviewed = !is_null($this->last_reviewed_at);
      $approvalStatus = $isApproved
            ? 'approved'
            : (($latestApprovalStatus === 'rejected' || $isReviewed) ? 'rejected' : 'pending');
      $queueAgeDays = $approvalStatus === 'pending' && $this->created_at
            ? $this->created_at->diffInDays(now())
            : null;
        
        return  [
                'id'=>$this->id,
                'user'=> new UserResource(User::find($this->user_id)),
                'user_id'=>$this->user_id,
                'hospital_id'=>$this->hospital_id,
                'hospital'=> new HospitalLiteResource(Hospital::find($this->hospital_id)),
                'bio'=>$this->bio,
                'position'=>$this->position,
                'rating'=>(String)number_format((Review::where('carer_id',$this->id)->avg('rating')), 1, '.', ''),
                'call_address'=>$this->call_address,
                 'appointments'=> AppointmentResource::collection(Appointment::where('carer_id',$this->id)
                 ->where('status','!=', 'rejected')->where('status','!=', 'finished')->get()),
                'reviews'=> ReviewResource::collection (Review::where('carer_id',$this->id)->get()),
                'consultations'=>ConsultLiteResource::collection(Consultation::where('carer_id',$this->id)->get()),
                'teletests'=>TeletestResource::collection(Teletest::where('carer_id',$this->id)->get()),
                'payments'=>PaymentResource::collection(Payment::where('carer_id',$this->id)->get()),
                'onHome_leave'=>$this->onHome_leave,
                'onVirtual_leave'=>$this->onVirtual_leave,
                'qualifications'=>$this->qualifications,
                'primary_qualification' => $this->primary_qualification,
                'specialties' => $this->specialties ?? [],
                'license_number' => $this->license_number,
                'issuing_body' => $this->issuing_body,
                'years_experience' => $this->years_experience,
                'virtual_day_time'=>$this->virtual_day_time,
                'home_day_time'=>$this->home_day_time,
                'admin_approved'=>$this->admin_approved,
                'super_admin_approved'=>$this->super_admin_approved,
                'approval_status' => $approvalStatus,
                'rejection_reason' => $rejectionReason,
                'last_reviewed_at' => optional($this->last_reviewed_at)->toDateTimeString(),
                'queue_age_days' => $queueAgeDays,
                'cert_lice'=>$this->cert_lice,
                'created_at'=>$this->created_at,
                'updated_at'=>$this->updated_at,
            ];
    }
}
