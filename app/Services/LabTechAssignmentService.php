<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Carer;
use App\Models\Consultation;
use App\Models\Teletest;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class LabTechAssignmentService
{
    public function findLeastBusy(int $hospitalId): ?array
    {
        $carers = Carer::where('hospital_id', $hospitalId)
            ->where('admin_approved', 1)
            ->where('super_admin_approved', 1)
            ->where('onHome_leave', 0)
            ->where('onVirtual_leave', 0)
            ->where('position', 'like', '%Lab%')
            ->get();

        if ($carers->isEmpty()) {
            return null;
        }

        $ranked = $carers->map(function (Carer $carer) {
            $load = $this->activeLoad($carer->id);

            return [
                'carer' => $carer,
                'load' => $load,
            ];
        })->sortBy(function (array $row) {
            return [$row['load'], -(float) $row['carer']->rating];
        })->values();

        $top = $ranked->first();

        return [
            'carer' => $top['carer'],
            'load' => $top['load'],
        ];
    }

    public function topMatches(
        int $hospitalId,
        int $limit = 3,
        ?float $lat = null,
        ?float $lon = null,
        string $visitType = 'home',
        string $availability = 'anytime',
        ?string $availabilityStart = null,
        ?string $availabilityEnd = null
    ): array
    {
        $carers = Carer::with('user', 'hospital')
            ->where('hospital_id', $hospitalId)
            ->where('admin_approved', 1)
            ->where('super_admin_approved', 1)
            ->where('position', 'like', '%Lab%')
            ->get();

        if ($carers->isEmpty()) {
            return [];
        }

        return $carers->filter(function (Carer $carer) use ($visitType, $availability, $availabilityStart, $availabilityEnd) {
            if ($visitType === 'home' && $carer->onHome_leave) {
                return false;
            }

            if ($visitType === 'virtual' && $carer->onVirtual_leave) {
                return false;
            }

            if ($availability === 'window' && $availabilityStart && $availabilityEnd) {
                return $this->isAvailableDuring($carer, $visitType, $availabilityStart, $availabilityEnd);
            }

            return true;
        })->map(function (Carer $carer) use ($lat, $lon) {
            return [
                'carer' => $carer,
                'load' => $this->activeLoad($carer->id),
                'distance_km' => $this->distanceForCarer($carer, $lat, $lon),
            ];
        })->sortBy(function (array $row) {
            return [
                $row['load'],
                $row['distance_km'] ?? PHP_FLOAT_MAX,
                -(float) $row['carer']->rating,
            ];
        })->values()->take($limit)->all();
    }

    private function activeLoad(int $carerId): int
    {
        $appointments = Appointment::where('carer_id', $carerId)
            ->whereNotIn('status', ['completed', 'cancelled', 'no_show'])
            ->count();

        $consultations = Consultation::where('carer_id', $carerId)
            ->whereNotIn('status', ['completed', 'cancelled', 'no_show'])
            ->count();

        $teletests = Teletest::where('carer_id', $carerId)
            ->whereNotIn('status', ['completed', 'cancelled', 'no_show'])
            ->count();

        return $appointments + $consultations + $teletests;
    }

    private function distanceForCarer(Carer $carer, ?float $lat, ?float $lon): ?float
    {
        if ($lat === null || $lon === null) {
            return null;
        }

        $userLat = $carer->user?->lat;
        $userLon = $carer->user?->lon;
        $hospitalLat = $carer->hospital?->lat;
        $hospitalLon = $carer->hospital?->lon;

        $targetLat = is_numeric($userLat) ? (float) $userLat : (is_numeric($hospitalLat) ? (float) $hospitalLat : null);
        $targetLon = is_numeric($userLon) ? (float) $userLon : (is_numeric($hospitalLon) ? (float) $hospitalLon : null);

        if ($targetLat === null || $targetLon === null) {
            return null;
        }

        return $this->haversine($lat, $lon, $targetLat, $targetLon);
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) ** 2 +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function isAvailableDuring(Carer $carer, string $visitType, string $start, string $end): bool
    {
        $schedule = $visitType === 'home' ? $carer->home_day_time : $carer->virtual_day_time;
        if (!$schedule) {
            return false;
        }

        $startAt = Carbon::parse($start);
        $endAt = Carbon::parse($end);
        if ($endAt->lessThanOrEqualTo($startAt)) {
            return false;
        }

        $dayKey = strtolower($startAt->format('D'));
        $scheduleData = json_decode($schedule, true);
        if (!is_array($scheduleData) || !isset($scheduleData[$dayKey])) {
            return false;
        }

        $day = $scheduleData[$dayKey];
        $avail = Arr::get($day, '0.avail', 'off');
        $startTime = Arr::get($day, '1.start');
        $stopTime = Arr::get($day, '2.stop');

        if ($avail !== 'on' || !$startTime || !$stopTime || $startTime === 'null' || $stopTime === 'null') {
            return false;
        }

        $slotStart = Carbon::parse($startAt->format('Y-m-d').' '.$startTime);
        $slotEnd = Carbon::parse($startAt->format('Y-m-d').' '.$stopTime);

        return $startAt->lessThanOrEqualTo($slotEnd) && $endAt->greaterThanOrEqualTo($slotStart);
    }
}
