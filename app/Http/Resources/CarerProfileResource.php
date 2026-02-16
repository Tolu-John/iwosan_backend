<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Review;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class CarerProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $user = $this->whenLoaded('user');
        $hospital = $this->whenLoaded('hospital');

        $avgRating = data_get($this, 'reviews_avg_rating');
        if ($avgRating === null) {
            $avgRating = Review::where('carer_id', $this->id)->avg('rating');
        }

        $reviewsCount = data_get($this, 'reviews_count');
        if ($reviewsCount === null) {
            $reviewsCount = Review::where('carer_id', $this->id)->count();
        }

        $appointmentsTotal = data_get($this, 'appointments_total_count');
        if ($appointmentsTotal === null) {
            $appointmentsTotal = Appointment::where('carer_id', $this->id)->count();
        }
        $appointmentsCompleted = data_get($this, 'appointments_completed_count');
        if ($appointmentsCompleted === null) {
            $appointmentsCompleted = Appointment::where('carer_id', $this->id)->where('status', 'completed')->count();
        }
        $appointmentsNoShow = data_get($this, 'appointments_no_show_count');
        if ($appointmentsNoShow === null) {
            $appointmentsNoShow = Appointment::where('carer_id', $this->id)->where('status', 'no_show')->count();
        }

        $consultationsTotal = data_get($this, 'consultations_total_count');
        if ($consultationsTotal === null) {
            $consultationsTotal = Consultation::where('carer_id', $this->id)->count();
        }
        $consultationsCompleted = data_get($this, 'consultations_completed_count');
        if ($consultationsCompleted === null) {
            $consultationsCompleted = Consultation::where('carer_id', $this->id)->where('status', 'completed')->count();
        }
        $consultationsNoShow = data_get($this, 'consultations_no_show_count');
        if ($consultationsNoShow === null) {
            $consultationsNoShow = Consultation::where('carer_id', $this->id)->where('status', 'no_show')->count();
        }

        $totalSessions = $appointmentsTotal + $consultationsTotal;
        $completedSessions = $appointmentsCompleted + $consultationsCompleted;
        $noShowSessions = $appointmentsNoShow + $consultationsNoShow;
        $completionRate = $totalSessions > 0 ? round(($completedSessions / $totalSessions) * 100, 1) : 0.0;
        $noShowRate = $totalSessions > 0 ? round(($noShowSessions / $totalSessions) * 100, 1) : 0.0;

        $distanceKm = null;
        $requestLat = $request->query('lat');
        $requestLon = $request->query('lon');
        $targetLat = $user?->lat ?? $hospital?->lat;
        $targetLon = $user?->lon ?? $hospital?->lon;
        if ($requestLat !== null && $requestLon !== null && $targetLat !== null && $targetLon !== null) {
            $distanceKm = round($this->haversineKm((float) $requestLat, (float) $requestLon, (float) $targetLat, (float) $targetLon), 2);
        }

        $nextAvailable = $this->resolveNextAvailableSlot();

        return [
            'id' => (string) $this->id,
            'user' => $user ? [
                'id' => (string) $user->id,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'user_img' => $user->user_img,
                'gender' => $user->gender,
            ] : null,
            'hospital' => $hospital ? [
                'id' => (string) $hospital->id,
                'name' => $hospital->name,
                'hospital_img' => $hospital->hospital_img,
                'address' => $hospital->address,
                'lat' => $hospital->lat,
                'lon' => $hospital->lon,
            ] : null,
            'bio' => $this->bio,
            'position' => $this->position,
            'rating' => number_format((float) $avgRating, 1, '.', ''),
            'reviews_count' => (int) $reviewsCount,
            'distance_km' => $distanceKm,
            'call_address' => $this->call_address,
            'qualifications' => $this->qualifications,
            'availability' => [
                'supports_home' => $this->home_day_time !== null,
                'supports_virtual' => $this->virtual_day_time !== null,
                'on_home_leave' => (bool) $this->onHome_leave,
                'on_virtual_leave' => (bool) $this->onVirtual_leave,
                'home_day_time' => $this->home_day_time,
                'virtual_day_time' => $this->virtual_day_time,
                'next_available' => $nextAvailable,
            ],
            'pricing' => [
                'home_visit_price' => $hospital?->home_visit_price,
                'virtual_visit_price' => $hospital?->virtual_visit_price,
                'virtual_ward_price' => $hospital?->virtual_ward_price,
            ],
            'performance' => [
                'total_sessions' => (int) $totalSessions,
                'completed_sessions' => (int) $completedSessions,
                'no_show_sessions' => (int) $noShowSessions,
                'completion_rate_pct' => $completionRate,
                'no_show_rate_pct' => $noShowRate,
                'response_time_minutes' => $this->response_time_minutes,
            ],
            'service_radius_km' => $this->service_radius_km,
            'verified' => (bool) ($this->admin_approved && $this->super_admin_approved),
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

    private function resolveNextAvailableSlot(): ?array
    {
        $now = Carbon::now();
        $candidates = [];

        if (!$this->onHome_leave) {
            $slot = $this->nextSlotFromSchedule($this->home_day_time, $now);
            if ($slot) {
                $candidates[] = ['type' => 'home', 'start_at' => $slot->toIso8601String()];
            }
        }

        if (!$this->onVirtual_leave) {
            $slot = $this->nextSlotFromSchedule($this->virtual_day_time, $now);
            if ($slot) {
                $candidates[] = ['type' => 'virtual', 'start_at' => $slot->toIso8601String()];
            }
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, function ($a, $b) {
            return strcmp($a['start_at'], $b['start_at']);
        });

        return $candidates[0];
    }

    private function nextSlotFromSchedule($rawSchedule, Carbon $now): ?Carbon
    {
        if (!$rawSchedule) {
            return null;
        }

        $schedule = $this->normalizeSchedule($rawSchedule);
        if (empty($schedule)) {
            return null;
        }

        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $currentDayIndex = (int) $now->format('N') - 1;

        for ($offset = 0; $offset < 7; $offset++) {
            $dayIndex = ($currentDayIndex + $offset) % 7;
            $dayKey = $days[$dayIndex];
            if (!isset($schedule[$dayKey]) || !is_array($schedule[$dayKey])) {
                continue;
            }

            foreach ($schedule[$dayKey] as $slot) {
                $start = $slot['start'] ?? null;
                if (!$start) {
                    continue;
                }

                $date = $now->copy()->startOfDay()->addDays($offset);
                $startAt = Carbon::parse($date->format('Y-m-d') . ' ' . $start);

                if ($startAt->greaterThanOrEqualTo($now)) {
                    return $startAt;
                }
            }
        }

        return null;
    }

    private function normalizeSchedule($rawSchedule): array
    {
        if (is_string($rawSchedule)) {
            $decoded = json_decode($rawSchedule, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->normalizeSchedule($decoded);
            }
            return [];
        }

        if (!is_array($rawSchedule)) {
            return [];
        }

        $normalized = [];
        foreach ($rawSchedule as $day => $slots) {
            $dayKey = strtolower((string) $day);
            if (!is_array($slots)) {
                continue;
            }

            $normalized[$dayKey] = [];
            foreach ($slots as $slot) {
                if (!is_array($slot)) {
                    continue;
                }
                $start = $slot['start'] ?? ($slot['from'] ?? null);
                $end = $slot['end'] ?? ($slot['to'] ?? null);
                if ($start) {
                    $normalized[$dayKey][] = [
                        'start' => $start,
                        'end' => $end,
                    ];
                }
            }
        }

        return $normalized;
    }
}
