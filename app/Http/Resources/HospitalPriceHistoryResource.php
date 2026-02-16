<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class HospitalPriceHistoryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => (string) $this->id,
            'hospital_id' => (string) $this->hospital_id,
            'previous' => [
                'home_visit_price' => $this->previous_home_visit_price,
                'virtual_visit_price' => $this->previous_virtual_visit_price,
                'virtual_ward_price' => $this->previous_virtual_ward_price,
            ],
            'current' => [
                'home_visit_price' => $this->home_visit_price,
                'virtual_visit_price' => $this->virtual_visit_price,
                'virtual_ward_price' => $this->virtual_ward_price,
            ],
            'reason' => $this->reason,
            'changed_by' => $this->changed_by,
            'changed_by_name' => $this->user ? trim($this->user->firstname.' '.$this->user->lastname) : null,
            'ip_address' => $this->ip_address,
            'created_at' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}
