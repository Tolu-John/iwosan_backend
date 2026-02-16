<?php

namespace App\Http\Resources;
use App\Models\Hospital;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class CarerLiteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {

        $user = User::find($this->user_id);
        $hospital = Hospital::find($this->hospital_id);
        $avgRating = Review::where('carer_id', $this->id)->avg('rating');
        $reviewCount = Review::where('carer_id', $this->id)->count();
        $approvalStatus = $this->admin_approved && $this->super_admin_approved
            ? 'approved'
            : ($this->admin_approved == 0 && $this->super_admin_approved == 0 ? 'rejected' : 'pending');
        $queueAgeDays = $approvalStatus === 'pending' && $this->created_at
            ? $this->created_at->diffInDays(now())
            : null;

        return  [
                'id'=>(string)$this->id,
                'user'=> new UserResource($user),
                'user_id'=>$this->user_id,
                'hospital_id'=>$this->hospital_id,
                'hospital'=> new HospitalLiteResource($hospital),
                'position'=>$this->position,
                'rating'=>(String)number_format((float) $avgRating, 1, '.', ''),
                'review_count' => (int) $reviewCount,
                'avatar' => $user?->user_img,
                'verified' => (bool) ($this->admin_approved && $this->super_admin_approved),
                'approval_status' => $approvalStatus,
                'last_reviewed_at' => optional($this->last_reviewed_at)->toDateTimeString(),
                'queue_age_days' => $queueAgeDays,
                'onHome_leave'=>$this->onHome_leave,
                'onVirtual_leave'=>$this->onVirtual_leave,
                'created_at'=>$this->created_at,
                'updated_at'=>$this->updated_at,
            ];
    }
}
