<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientLiteResource extends JsonResource
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

        return [
            'id'=>(string)$this->id,
                'user'=> new UserResource($user),
                'user_id'=>$this->user_id,
                'address'=> $this->address,
                'avatar' => $user?->user_img,
                'verified' => true,
                'push_notifications_enabled' => (bool) $this->push_notifications_enabled,
                'sms_alerts_enabled' => (bool) $this->sms_alerts_enabled,
                'share_vitals_with_carers' => (bool) $this->share_vitals_with_carers,
    ];
    }
}
