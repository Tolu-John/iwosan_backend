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
    ];
    }
}
