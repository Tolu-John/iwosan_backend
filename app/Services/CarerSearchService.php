<?php

namespace App\Services;

use App\Models\Carer;
use Illuminate\Support\Facades\DB;

class CarerSearchService
{
    public function search(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);

        $query = Carer::query()
            ->join('users', 'users.id', '=', 'carers.user_id')
            ->join('hospitals', 'hospitals.id', '=', 'carers.hospital_id')
            ->where('carers.admin_approved', 1)
            ->where('carers.super_admin_approved', 1);

        if (!empty($filters['hospital_id'])) {
            $query->where('carers.hospital_id', (int) $filters['hospital_id']);
        }

        if (!empty($filters['gender'])) {
            $query->where('users.gender', $filters['gender']);
        }

        if (!empty($filters['position'])) {
            $query->where('carers.position', 'like', '%'.$filters['position'].'%');
        }

        if (!empty($filters['hospital_name'])) {
            $query->where('hospitals.name', 'like', '%'.$filters['hospital_name'].'%');
        }

        if (!empty($filters['q'])) {
            $tokens = preg_split('/\s+/', $filters['q']);
            $query->where(function ($q) use ($tokens) {
                foreach ($tokens as $token) {
                    if ($token === '') {
                        continue;
                    }
                    $q->where(function ($sub) use ($token) {
                        $sub->where('users.firstname', 'like', '%'.$token.'%')
                            ->orWhere('users.lastname', 'like', '%'.$token.'%')
                            ->orWhere('carers.position', 'like', '%'.$token.'%')
                            ->orWhere('hospitals.name', 'like', '%'.$token.'%');
                    });
                }
            });
        }

        $visitType = $filters['visit_type'];
        if ($visitType === 'home') {
            $query->where('carers.onHome_leave', 0);
        } else {
            $query->where('carers.onVirtual_leave', 0);
        }

        if (!empty($filters['price_min']) || !empty($filters['price_max'])) {
            $priceColumn = $visitType === 'home' ? 'hospitals.home_visit_price' : 'hospitals.virtual_visit_price';
            if (!empty($filters['price_min'])) {
                $query->where($priceColumn, '>=', (float) $filters['price_min']);
            }
            if (!empty($filters['price_max'])) {
                $query->where($priceColumn, '<=', (float) $filters['price_max']);
            }
        }

        $lat = $filters['lat'] ?? null;
        $lon = $filters['lon'] ?? null;
        if ($lat !== null && $lon !== null) {
            $query->selectRaw($this->distanceSql(), [$lat, $lon, $lat])
                ->addSelect(['carers.*', 'users.firstname', 'users.lastname', 'users.gender', 'users.phone', 'users.email', 'users.lat as user_lat', 'users.lon as user_lon'])
                ->addSelect(['hospitals.name as hospital_name', 'hospitals.home_visit_price', 'hospitals.virtual_visit_price', 'hospitals.lat as hospital_lat', 'hospitals.lon as hospital_lon']);

            if (!empty($filters['max_distance_km'])) {
                $query->having('distance_km', '<=', (float) $filters['max_distance_km']);
            }
        } else {
            $query->addSelect(['carers.*', 'users.firstname', 'users.lastname', 'users.gender', 'users.phone', 'users.email', 'users.lat as user_lat', 'users.lon as user_lon'])
                ->addSelect(['hospitals.name as hospital_name', 'hospitals.home_visit_price', 'hospitals.virtual_visit_price', 'hospitals.lat as hospital_lat', 'hospitals.lon as hospital_lon']);
        }

        $query->addSelect([
            DB::raw("(select count(*) from certlices where type = 'carer' and type_id = carers.id and status = 'verified') as certifications_count"),
            DB::raw('(select count(*) from reviews where carer_id = carers.id) as review_count'),
        ]);

        $scoreBindings = $this->scoreBindings($filters);
        $query->selectRaw($this->scoreSql(), $scoreBindings);

        $query->orderByDesc('match_score');
        if ($lat !== null && $lon !== null) {
            $query->orderBy('distance_km');
        }
        $query->orderByDesc('rating');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $results = $paginator->getCollection()->map(function ($row) use ($visitType) {
            return [
                'id' => (string) $row->id,
                'user' => [
                    'firstname' => $row->firstname,
                    'lastname' => $row->lastname,
                    'gender' => $row->gender,
                    'phone' => $row->phone,
                    'email' => $row->email,
                    'lat' => $row->user_lat,
                    'lon' => $row->user_lon,
                ],
                'hospital' => [
                    'id' => (string) $row->hospital_id,
                    'name' => $row->hospital_name,
                    'home_visit_price' => $row->home_visit_price,
                    'virtual_visit_price' => $row->virtual_visit_price,
                    'lat' => $row->hospital_lat,
                    'lon' => $row->hospital_lon,
                ],
                'position' => $row->position,
                'rating' => $row->rating,
                'verified' => (bool) ($row->admin_approved && $row->super_admin_approved),
                'certifications_count' => (int) $row->certifications_count,
                'review_count' => (int) $row->review_count,
                'distance_km' => isset($row->distance_km) ? (float) $row->distance_km : null,
                'price' => $visitType === 'home' ? $row->home_visit_price : $row->virtual_visit_price,
                'next_available' => $visitType === 'home' ? $row->home_day_time : $row->virtual_day_time,
                'match_score' => (float) $row->match_score,
            ];
        })->values();

        return [
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'results' => $results,
        ];
    }

    private function distanceSql(): string
    {
        return "(
            6371 * acos(
                cos(radians(?)) * cos(radians(COALESCE(NULLIF(users.lat, ''), NULLIF(hospitals.lat, ''))))
                * cos(radians(COALESCE(NULLIF(users.lon, ''), NULLIF(hospitals.lon, ''))) - radians(?))
                + sin(radians(?)) * sin(radians(COALESCE(NULLIF(users.lat, ''), NULLIF(hospitals.lat, ''))))
            )
        ) as distance_km";
    }

    private function scoreSql(): string
    {
        return "(
            (coalesce(carers.rating, 0) * 2) +
            (case when ? is not null and carers.position like ? then 2 else 0 end) +
            (case when ? is not null and (users.firstname like ? or users.lastname like ?) then 2 else 0 end) +
            (case when ? is not null and hospitals.name like ? then 1 else 0 end) +
            (case when carers.onHome_leave = 0 or carers.onVirtual_leave = 0 then 1 else 0 end) +
            (select count(*) from certlices where type = 'carer' and type_id = carers.id and status = 'verified') * 0.2
        ) as match_score";
    }

    private function scoreBindings(array $filters): array
    {
        $position = $filters['position'] ?? null;
        $q = $filters['q'] ?? null;
        $hospitalName = $filters['hospital_name'] ?? null;

        $positionLike = $position ? '%'.$position.'%' : null;
        $qLike = $q ? '%'.$q.'%' : null;
        $hospitalLike = $hospitalName ? '%'.$hospitalName.'%' : null;

        return [
            $position,
            $positionLike,
            $q,
            $qLike,
            $qLike,
            $hospitalName,
            $hospitalLike,
        ];
    }
}
