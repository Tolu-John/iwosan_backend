<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CarerApprovalLogResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => (string) $this->id,
            'carer_id' => (string) $this->carer_id,
            'hospital_id' => (string) $this->hospital_id,
            'status' => $this->status,
            'reason' => $this->reason,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_by_name' => $this->reviewer ? trim($this->reviewer->firstname.' '.$this->reviewer->lastname) : null,
            'created_at' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
