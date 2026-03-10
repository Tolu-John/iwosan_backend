<?php

namespace App\Http\Resources;

use App\Models\CarerApprovalLog;
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
            'id' => (string) $this->id,
            'user' => [
                'id' => (string) ($user?->id ?? ''),
                'firstname' => $user?->firstname,
                'lastname' => $user?->lastname,
                'email' => $user?->email,
                'user_img' => $user?->user_img,
                'phone' => $user?->phone,
                'gender' => $user?->gender,
                'address' => $user?->address,
                'lat' => $user?->lat,
                'lon' => $user?->lon,
            ],
            'user_id' => $this->user_id,
            'hospital_id' => $this->hospital_id,
            'hospital' => [
                'id' => (string) ($hospital?->id ?? ''),
                'name' => $hospital?->name,
                'code' => $hospital?->code,
                'phone' => $hospital?->phone,
                'email' => $hospital?->email,
                'address' => $hospital?->address,
            ],
            'position' => $this->position,
            'primary_qualification' => $this->primary_qualification,
            'qualifications' => $this->qualifications,
            'license_number' => $this->license_number,
            'rating' => (String) number_format((float) $avgRating, 1, '.', ''),
            'review_count' => (int) $reviewCount,
            'avatar' => $user?->user_img,
            'verified' => (bool) ($this->admin_approved && $this->super_admin_approved),
            'super_admin_approved' => (int) $this->super_admin_approved,
            'approval_status' => $approvalStatus,
            'rejection_reason' => $rejectionReason,
            'last_reviewed_at' => optional($this->last_reviewed_at)->toDateTimeString(),
            'queue_age_days' => $queueAgeDays,
            'onHome_leave' => $this->onHome_leave,
            'onVirtual_leave' => $this->onVirtual_leave,
            'updated_at' => $this->updated_at,
        ];
    }
}
