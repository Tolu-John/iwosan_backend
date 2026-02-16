<?php

namespace App\Services;

use App\Models\test;
use Illuminate\Support\Facades\DB;

class TestSearchService
{
    public function search(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);

        $query = test::query()
            ->join('hospitals', 'hospitals.id', '=', 'tests.hospital_id')
            ->where('hospitals.super_admin_approved', 1)
            ->where('tests.is_active', 1);

        if (!empty($filters['hospital_id'])) {
            $query->where('tests.hospital_id', (int) $filters['hospital_id']);
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
                        $sub->where('tests.name', 'like', '%'.$token.'%')
                            ->orWhere('hospitals.name', 'like', '%'.$token.'%');
                    });
                }
            });
        }

        if (!empty($filters['price_min'])) {
            $query->where('tests.price', '>=', (float) $filters['price_min']);
        }
        if (!empty($filters['price_max'])) {
            $query->where('tests.price', '<=', (float) $filters['price_max']);
        }

        if (!empty($filters['rating_min'])) {
            $query->where('hospitals.rating', '>=', (float) $filters['rating_min']);
        }

        $lat = $filters['lat'] ?? null;
        $lon = $filters['lon'] ?? null;
        if ($lat !== null && $lon !== null) {
            $query->selectRaw($this->distanceSql(), [$lat, $lon, $lat])
                ->addSelect(['tests.*', 'hospitals.name as hospital_name', 'hospitals.rating as hospital_rating', 'hospitals.lat as hospital_lat', 'hospitals.lon as hospital_lon']);

            if (!empty($filters['max_distance_km'])) {
                $query->having('distance_km', '<=', (float) $filters['max_distance_km']);
            }
        } else {
            $query->addSelect(['tests.*', 'hospitals.name as hospital_name', 'hospitals.rating as hospital_rating', 'hospitals.lat as hospital_lat', 'hospitals.lon as hospital_lon']);
        }

        $scoreBindings = $this->scoreBindings($filters);
        $query->selectRaw($this->scoreSql(), $scoreBindings);

        $query->orderByDesc('match_score');
        if ($lat !== null && $lon !== null) {
            $query->orderBy('distance_km');
        }
        $query->orderBy('tests.price');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $results = $paginator->getCollection()->map(function ($row) {
            return [
                'id' => (string) $row->id,
                'name' => $row->name,
                'price' => $row->price,
                'extra_notes' => $row->extra_notes,
                'hospital' => [
                    'id' => (string) $row->hospital_id,
                    'name' => $row->hospital_name,
                    'rating' => $row->hospital_rating,
                    'lat' => $row->hospital_lat,
                    'lon' => $row->hospital_lon,
                ],
                'distance_km' => isset($row->distance_km) ? (float) $row->distance_km : null,
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
                cos(radians(?)) * cos(radians(COALESCE(NULLIF(hospitals.lat, ''), 0)))
                * cos(radians(COALESCE(NULLIF(hospitals.lon, ''), 0)) - radians(?))
                + sin(radians(?)) * sin(radians(COALESCE(NULLIF(hospitals.lat, ''), 0)))
            )
        ) as distance_km";
    }

    private function scoreSql(): string
    {
        return "(
            (coalesce(hospitals.rating, 0) * 2) +
            (case when ? is not null and tests.name like ? then 2 else 0 end) +
            (case when ? is not null and hospitals.name like ? then 1 else 0 end)
        ) as match_score";
    }

    private function scoreBindings(array $filters): array
    {
        $q = $filters['q'] ?? null;
        $hospitalName = $filters['hospital_name'] ?? null;

        $qLike = $q ? '%'.$q.'%' : null;
        $hospitalLike = $hospitalName ? '%'.$hospitalName.'%' : null;

        return [
            $q,
            $qLike,
            $hospitalName,
            $hospitalLike,
        ];
    }
}
