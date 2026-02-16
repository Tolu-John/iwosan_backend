<?php

namespace App\Services;

use App\Http\Resources\CarerLiteResource;
use App\Models\Carer;
use App\Models\Consultation;
use App\Models\Teletest;
use App\Models\Transfers;
use App\Models\VConsultation;
use App\Models\ward;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class MetricsService
{
    public function carerMetrics(int $carerId, ?string $from = null, ?string $to = null, ?string $tz = null): array
    {
        $range = $this->normalizeRange($from, $to, $tz);
        $cacheKey = $this->cacheKey('carer_metrics', $carerId, $range);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($carerId, $range) {
            $transfers = Transfers::where('carer_id', $carerId);
            if ($range['from']) {
                $transfers->whereDate('created_at', '>=', $range['from']);
            }
            if ($range['to']) {
                $transfers->whereDate('created_at', '<=', $range['to']);
            }

            $general_amount_made = (clone $transfers)->sum('amount');

            $amount_made_virtual = (clone $transfers)
                ->where('reason', 'Virtual visit')
                ->sum('amount');

            $virtualConsultations = Consultation::where('carer_id', $carerId)
                ->where('treatment_type', 'Virtual visit')
                ->when($range['from'], function ($query) use ($range) {
                    return $query->whereDate('date_time', '>=', $range['from']);
                })
                ->when($range['to'], function ($query) use ($range) {
                    return $query->whereDate('date_time', '<=', $range['to']);
                });

            $total_visits_virtual = (clone $virtualConsultations)->count();

            $consultationIds = (clone $virtualConsultations)->pluck('id');
            $durations = $consultationIds->isEmpty()
                ? collect()
                : VConsultation::whereIn('consultation_id', $consultationIds)->pluck('duration');
            $totalDurationSeconds = $this->sumDurationSeconds($durations);
            $totalDuration = $this->formatDuration($totalDurationSeconds);

            $values_virtual = $this->dateCounts($virtualConsultations, 'date_time');

            $amount_made_home = (clone $transfers)
                ->where('reason', 'Home visit')
                ->sum('amount');

            $homeConsultations = Consultation::where('carer_id', $carerId)
                ->whereIn('treatment_type', ['Home visit', 'Home visit Admitted'])
                ->when($range['from'], function ($query) use ($range) {
                    return $query->whereDate('date_time', '>=', $range['from']);
                })
                ->when($range['to'], function ($query) use ($range) {
                    return $query->whereDate('date_time', '<=', $range['to']);
                });

            $total_visit_home = (clone $homeConsultations)->count();
            $values_home = $this->dateCounts($homeConsultations, 'date_time');

            $wardAdmissions = ward::where('carer_id', $carerId)
                ->when($range['from'], function ($query) use ($range) {
                    return $query->whereDate('admission_date', '>=', $range['from']);
                })
                ->when($range['to'], function ($query) use ($range) {
                    return $query->whereDate('admission_date', '<=', $range['to']);
                });

            $total_admissions = (clone $wardAdmissions)->count();
            $values_admis_ward = $this->dateCounts($wardAdmissions, 'admission_date');

            $wardDischarges = ward::where('carer_id', $carerId)
                ->whereNotNull('discharge_date')
                ->when($range['from'], function ($query) use ($range) {
                    return $query->whereDate('discharge_date', '>=', $range['from']);
                })
                ->when($range['to'], function ($query) use ($range) {
                    return $query->whereDate('discharge_date', '<=', $range['to']);
                });
            $values_disch_ward = $this->dateCounts($wardDischarges, 'discharge_date');

            $amount_made_test = (clone $transfers)
                ->where('reason', 'tele_test')
                ->sum('amount');

            $teletests = Teletest::where('carer_id', $carerId)
                ->when($range['from'], function ($query) use ($range) {
                    return $query->whereDate('date_time', '>=', $range['from']);
                })
                ->when($range['to'], function ($query) use ($range) {
                    return $query->whereDate('date_time', '<=', $range['to']);
                });

            $total_test = (clone $teletests)->count();
            $values_test = $this->dateCounts($teletests, 'date_time');

            return [
                'range' => $range,
                'amount_made_test' => $amount_made_test,
                'total_test' => $total_test,
                'values_test' => $values_test,
                'amount_made_home' => $amount_made_home,
                'total_visit_home' => $total_visit_home,
                'values_home' => $values_home,
                'carer_id' => (string) $carerId,
                'general_amount_made' => $general_amount_made,
                'amount_made_virtual' => $amount_made_virtual,
                'total_visits_virtual' => $total_visits_virtual,
                'total_duration_seconds' => $totalDurationSeconds,
                'total_duration' => $totalDuration,
                'values_virtual' => $values_virtual,
                'total_admissions' => $total_admissions,
                'values_admis' => $values_admis_ward,
                'values_disch' => $values_disch_ward,
            ];
        });
    }

    public function hospitalMetrics(int $hospitalId, ?string $from = null, ?string $to = null, ?string $tz = null): array
    {
        $range = $this->normalizeRange($from, $to, $tz);
        $cacheKey = $this->cacheKey('hospital_metrics', $hospitalId, $range);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($hospitalId, $range) {
            $transfers = Transfers::where('hospital_id', $hospitalId);
            if ($range['from']) {
                $transfers->whereDate('created_at', '>=', $range['from']);
            }
            if ($range['to']) {
                $transfers->whereDate('created_at', '<=', $range['to']);
            }

            $general_amount_made = (clone $transfers)->sum('amount');

            $top_rating = Carer::where('hospital_id', $hospitalId)->max('rating');
            $top_carer_id = Carer::select(['id'])
                ->where('rating', $top_rating)
                ->where('hospital_id', $hospitalId)
                ->first();

            $top_carer = null;
            if (isset($top_carer_id)) {
                $top_carer = new CarerLiteResource(Carer::find($top_carer_id['id']));
            }

            $amount_made_virtual = (clone $transfers)
                ->where('reason', 'Virtual visit')
                ->sum('amount');

            $virtualConsultations = Consultation::where('hospital_id', $hospitalId)
                ->where('treatment_type', 'Virtual visit')
                ->when($range['from'], function ($query) use ($range) {
                    return $query->whereDate('date_time', '>=', $range['from']);
                })
                ->when($range['to'], function ($query) use ($range) {
                    return $query->whereDate('date_time', '<=', $range['to']);
                });

            $total_visits_virtual = (clone $virtualConsultations)->count();

            $consultationIds = (clone $virtualConsultations)->pluck('id');
            $durations = $consultationIds->isEmpty()
                ? collect()
                : VConsultation::whereIn('consultation_id', $consultationIds)->pluck('duration');
            $totalDurationSeconds = $this->sumDurationSeconds($durations);
            $totalDuration = $this->formatDuration($totalDurationSeconds);

            $values_virtual = $this->dateCounts($virtualConsultations, 'date_time');

            $amount_made_home = (clone $transfers)
                ->where('reason', 'Home visit')
                ->sum('amount');

            $homeConsultations = Consultation::where('hospital_id', $hospitalId)
                ->whereIn('treatment_type', ['Home visit', 'Home visit Admitted'])
                ->when($range['from'], function ($query) use ($range) {
                    return $query->whereDate('date_time', '>=', $range['from']);
                })
                ->when($range['to'], function ($query) use ($range) {
                    return $query->whereDate('date_time', '<=', $range['to']);
                });

            $total_visit_home = (clone $homeConsultations)->count();
            $values_home = $this->dateCounts($homeConsultations, 'date_time');

            $wardAdmissions = ward::where('hospital_id', $hospitalId)
                ->when($range['from'], function ($query) use ($range) {
                    return $query->whereDate('admission_date', '>=', $range['from']);
                })
                ->when($range['to'], function ($query) use ($range) {
                    return $query->whereDate('admission_date', '<=', $range['to']);
                });

            $total_admissions = (clone $wardAdmissions)->count();
            $values_admis_ward = $this->dateCounts($wardAdmissions, 'admission_date');

            $wardDischarges = ward::where('hospital_id', $hospitalId)
                ->whereNotNull('discharge_date')
                ->when($range['from'], function ($query) use ($range) {
                    return $query->whereDate('discharge_date', '>=', $range['from']);
                })
                ->when($range['to'], function ($query) use ($range) {
                    return $query->whereDate('discharge_date', '<=', $range['to']);
                });
            $values_disch_ward = $this->dateCounts($wardDischarges, 'discharge_date');

            $amount_made_test = (clone $transfers)
                ->where('reason', 'tele_test')
                ->sum('amount');

            $teletests = Teletest::where('hospital_id', $hospitalId)
                ->when($range['from'], function ($query) use ($range) {
                    return $query->whereDate('date_time', '>=', $range['from']);
                })
                ->when($range['to'], function ($query) use ($range) {
                    return $query->whereDate('date_time', '<=', $range['to']);
                });

            $total_test = (clone $teletests)->count();
            $values_test = $this->dateCounts($teletests, 'date_time');

            return [
                'range' => $range,
                'amount_made_test' => $amount_made_test,
                'total_test' => $total_test,
                'values_test' => $values_test,
                'amount_made_home' => $amount_made_home,
                'total_visit_home' => $total_visit_home,
                'values_home' => $values_home,
                'hospital_id' => (string) $hospitalId,
                'general_amount_made' => $general_amount_made,
                'top_carer' => $top_carer,
                'amount_made_virtual' => $amount_made_virtual,
                'total_visits_virtual' => $total_visits_virtual,
                'total_duration_seconds' => $totalDurationSeconds,
                'total_duration' => $totalDuration,
                'values_virtual' => $values_virtual,
                'total_admissions' => $total_admissions,
                'values_admis' => $values_admis_ward,
                'values_disch' => $values_disch_ward,
            ];
        });
    }

    private function dateCounts($query, string $dateColumn): array
    {
        $rows = (clone $query)
            ->select(DB::raw("DATE($dateColumn) as date"), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $rows->map(function ($row) {
            return [
                'date' => $row->date,
                'count' => (int) $row->count,
            ];
        })->values()->all();
    }

    private function sumDurationSeconds($durations): int
    {
        $total = 0;
        foreach ($durations as $duration) {
            if (!$duration) {
                continue;
            }
            $parts = explode(':', $duration);
            if (count($parts) === 3) {
                $total += ((int) $parts[0] * 3600) + ((int) $parts[1] * 60) + (int) $parts[2];
            }
        }
        return $total;
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '00:00:00';
        }
        return gmdate('H:i:s', $seconds);
    }

    private function normalizeRange(?string $from, ?string $to, ?string $tz): array
    {
        $timezone = $tz ?: 'UTC';
        $fromDate = $from ? Carbon::parse($from, $timezone)->toDateString() : null;
        $toDate = $to ? Carbon::parse($to, $timezone)->toDateString() : null;

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'timezone' => $timezone,
        ];
    }

    private function cacheKey(string $prefix, int $id, array $range): string
    {
        $suffix = implode(':', [
            $range['from'] ?? 'null',
            $range['to'] ?? 'null',
            $range['timezone'] ?? 'UTC',
        ]);

        return "{$prefix}_{$id}_{$suffix}";
    }
}
