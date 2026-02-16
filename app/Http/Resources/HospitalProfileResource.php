<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Resources\Json\JsonResource;

class HospitalProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $carers = $this->whenLoaded('carer');

        $carerIds = $carers ? $carers->pluck('id') : null;
        $rating = 0.0;
        if ($carerIds && $carerIds->isNotEmpty()) {
            $rating = (float) Review::whereIn('carer_id', $carerIds)->avg('rating');
        }

        $distanceKm = null;
        $requestLat = $request->query('lat');
        $requestLon = $request->query('lon');
        if ($requestLat !== null && $requestLon !== null && $this->lat !== null && $this->lon !== null) {
            $distanceKm = round($this->haversineKm((float) $requestLat, (float) $requestLon, (float) $this->lat, (float) $this->lon), 2);
        }

        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'rating' => number_format($rating, 1, '.', ''),
            'carers_count' => (int) ($carers ? $carers->count() : $this->carer()->count()),
            'distance_km' => $distanceKm,
            'about_us' => $this->about_us,
            'website' => $this->website,
            'hospital_img' => $this->hospital_img,
            'phone' => $this->phone,
            'email' => $this->email,
            'pricing' => [
                'home_visit_price' => $this->home_visit_price,
                'virtual_visit_price' => $this->virtual_visit_price,
                'virtual_ward_price' => $this->virtual_ward_price,
            ],
            'home_visit_price' => $this->home_visit_price,
            'virtual_visit_price' => $this->virtual_visit_price,
            'virtual_ward_price' => $this->virtual_ward_price,
            'lat' => $this->lat,
            'lon' => $this->lon,
            'address' => $this->address,
            'verified' => (bool) $this->super_admin_approved,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * asin(min(1, sqrt($a)));

        return $earthRadius * $c;
    }
}
