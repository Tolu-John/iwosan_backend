<?php

namespace App\Http\Controllers;

use App\Http\Resources\AppointmentResource;
use App\Http\Resources\CarerLiteResource;
use App\Http\Resources\CarerResource;
use App\Http\Resources\CertliceStaffResource;
use App\Http\Resources\ConsultationResource;
use App\Http\Resources\ConsultLiteResource;
use App\Http\Resources\PatientLiteResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\TeletestResource;
use App\Http\Resources\WardResource;
use App\Http\Resources\WardLiteResource;
use App\Http\Resources\ReviewResource;
use App\Models\Appointment;
use App\Models\Carer;
use App\Models\Certlice;
use App\Models\Consultation;
use App\Models\other_vitals;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Teletest;
use App\Models\User;
use App\Models\ward;
use App\Models\ward_bp_dia;
use App\Models\ward_bp_sys;
use App\Models\ward_sugar;
use App\Models\ward_temp;
use App\Models\ward_weight;
use App\Models\vital_alert_limit;
use App\Services\AccessService;
use App\Services\MetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class DashboardControllerC extends Controller
{
    private AccessService $access;
    private MetricsService $metricsService;

    public function __construct(AccessService $access, MetricsService $metricsService)
    {
        $this->access = $access;
        $this->metricsService = $metricsService;
    }

    private function requireCarerId($id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $carer = Carer::where('user_id', $user->id)->first();
        if (!$carer || (int) $carer->id !== (int) $id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $carer;
    }

    public function showappointment($id){
        $auth = $this->requireCarerId($id);
        if ($auth instanceof \Illuminate\Http\Response) {
            return $auth;
        }
    
        $result_arr=array();
    
    $appointments=Appointment::where('carer_id',$id)
    ->where('status','!=', 'rejected')
    ->where('status','!=', 'finished')
    ->where('admin_approved',1)
    ->orderBy('updated_at', 'desc')
    ->get();
    
    if (is_null($appointments)) {
        return $this->sendError('Appointment not found.');
        }
    
      
    
        return (response( AppointmentResource::collection($appointments)
        , 200));
    
    }

    public function getwardAdmissions(Request $request, $id){
        $auth = $this->requireCarerId($id);
        if ($auth instanceof \Illuminate\Http\Response) {
            return $auth;
        }
    
        $status = $request->query('status', 'active');
        $priority = $request->query('priority');
        $q = $request->query('q');
        $sort = $request->query('sort', 'recent');
        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);

        $query = ward::with(['patient', 'carer'])
            ->where('carer_id', $id);

        if ($status === 'active') {
            $query->where('discharged', 0);
        } elseif ($status === 'discharged') {
            $query->where('discharged', 1);
        }

        if ($priority) {
            $query->where('priority', $priority);
        }

        if ($q) {
            $query->whereHas('patient.user', function ($sub) use ($q) {
                $sub->where('firstname', 'like', '%'.$q.'%')
                    ->orWhere('lastname', 'like', '%'.$q.'%');
            })->orWhere('diagnosis', 'like', '%'.$q.'%');
        }

        if ($sort === 'priority') {
            $query->orderByRaw("case priority when 'high' then 1 when 'medium' then 2 else 3 end");
            $query->orderBy('admission_date', 'desc');
        } else {
            $query->orderBy('admission_date', 'desc');
        }

        $wards = $query->paginate($perPage, ['*'], 'page', $page);

        $data = $wards->getCollection()->map(function ($ward) {
            $latestVitalsAt = $this->latestVitalsTimestamp($ward->id);
            $alertsCount = $this->alertsCount($ward->id);

            return [
                'id' => (string) $ward->id,
                'patient_id' => $ward->patient_id,
                'patient' => new PatientLiteResource($ward->patient),
                'carer_id' => $ward->carer_id,
                'carer' => new CarerLiteResource($ward->carer),
                'hospital_id' => $ward->hospital_id,
                'appt_id' => $ward->appt_id,
                'diagnosis' => $ward->diagnosis,
                'admission_date' => $ward->admission_date,
                'discharged' => $ward->discharged,
                'discharge_date' => $ward->discharge_date,
                'priority' => $ward->priority,
                'latest_vitals_at' => $latestVitalsAt,
                'alerts_count' => $alertsCount,
            ];
        })->values();

        return response([
            'data' => $data,
            'pagination' => [
                'total' => $wards->total(),
                'page' => $wards->currentPage(),
                'per_page' => $wards->perPage(),
                'last_page' => $wards->lastPage(),
            ],
        ], 200);
    
    }

    public function carerPatients($id)
    {
        $auth = $this->requireCarerId($id);
        if ($auth instanceof \Illuminate\Http\Response) {
            return $auth;
        }

        $patientIds = collect($this->access->accessiblePatientIds())
            ->filter(fn ($value) => !is_null($value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        if ($patientIds->isEmpty()) {
            return response(['data' => []], 200);
        }

        $patients = Patient::with('user')
            ->whereIn('id', $patientIds->all())
            ->orderByDesc('updated_at')
            ->get();

        $data = $patients->map(function ($patient) use ($id) {
            $user = $patient->user;
            $name = trim((string) (($user->firstname ?? '').' '.($user->lastname ?? '')));
            if ($name === '') {
                $name = 'Patient #'.$patient->id;
            }

            $latestAppointment = Appointment::where('carer_id', $id)
                ->where('patient_id', $patient->id)
                ->orderByDesc('updated_at')
                ->first();

            $status = strtolower((string) optional($latestAppointment)->status);
            $isPriority = in_array($status, ['assigned', 'en_route', 'arrived', 'in_progress', 'home_admitted'], true);

            return [
                'id' => (int) $patient->id,
                'name' => $name,
                'phone' => $user->phone,
                'risk' => $isPriority ? 'medium' : 'low',
                'workflow_status' => $status !== '' ? $status : 'stable',
                'next_action' => 'Record Vitals',
                'due_text' => $latestAppointment && !empty($latestAppointment->date_time)
                    ? 'Visit: '.$latestAppointment->date_time
                    : 'No due time',
            ];
        })->values();

        return response(['data' => $data], 200);
    }


    public function showpaymentsbymypatients($id){
        $auth = $this->requireCarerId($id);
        if ($auth instanceof \Illuminate\Http\Response) {
            return $auth;
        }

        $patientIds = $this->access->accessiblePatientIds();

        $payments = Payment::where('carer_id', $id)
            ->whereIn('patient_id', $patientIds)
            ->orderBy('updated_at', 'desc')
            ->get();
        
        if (is_null($payments)) {
            return $this->sendError('Payments not found.');
            }
        
        return (response(PaymentResource::collection($payments)
        , 200));
        
        }

        public function carerMetrics($id){
            $auth = $this->requireCarerId($id);
            if ($auth instanceof \Illuminate\Http\Response) {
                return $auth;
            }

            $validator = Validator::make(request()->all(), [
                'from' => 'nullable|date',
                'to' => 'nullable|date',
                'tz' => 'nullable|string|max:60',
            ]);

            if ($validator->fails()) {
                return response(['Validation errors' => $validator->errors()->all()], 422);
            }

            $data = $validator->validated();

            return response($this->metricsService->carerMetrics(
                (int) $id,
                $data['from'] ?? null,
                $data['to'] ?? null,
                $data['tz'] ?? null
            ), 200);
        }

        public function myrecordvconsult($id){
            $auth = $this->requireCarerId($id);
            if ($auth instanceof \Illuminate\Http\Response) {
                return $auth;
            }

            $result_arr=array();
        
        // vconsult lite resource
         $consultations = Consultation::where('carer_id',$id)
         ->where('treatment_type','Virtual visit')       
         ->orderBy('updated_at', 'desc')->get();
        
        
        return (response(  ConsultLiteResource::collection($consultations)
        , 200));
        }


        public function myrecordhconsult($id){
            $auth = $this->requireCarerId($id);
            if ($auth instanceof \Illuminate\Http\Response) {
                return $auth;
            }

            $result_arr=array();
        
        // hconsult lite resource
         $consultations = Consultation::where('carer_id',$id)
            ->whereIn('treatment_type', ['Home visit', 'Home visit Admitted'])
         ->orderBy('updated_at', 'desc')->get();
        
        
        return (response(ConsultLiteResource::collection($consultations)
        , 200));
        }
        
        public function myrecordteletest($id){
                $auth = $this->requireCarerId($id);
                if ($auth instanceof \Illuminate\Http\Response) {
                    return $auth;
                }
        
                $result_arr=array();
            
            // hconsult lite resource
             $teletests = Teletest::where('carer_id',$id)     
             ->orderBy('updated_at', 'desc')->get();
            
            
            return (response( TeletestResource::collection($teletests)
            , 200));
        }

        public function carerlite($id){
            $auth = $this->requireCarerId($id);
            if ($auth instanceof \Illuminate\Http\Response) {
                return $auth;
            }

            $carer= Carer::find($id);
        
            return response( new CarerLiteResource($carer),200);
        
        }

        public function carer_certs(Request $request, $id){
            $auth = $this->requireCarerId($id);
            if ($auth instanceof \Illuminate\Http\Response) {
                return $auth;
            }

            $status = $request->query('status');
            $certsQuery = Certlice::where('type', 'carer')->where('type_id', $id);
            if ($status) {
                $certsQuery->where('status', $status);
            }
            $certs = $certsQuery->orderBy('updated_at', 'desc')->get();

            $summary = $this->certSummary($certsQuery->clone());

            return response([
                'summary' => $summary,
                'certificates' => CertliceStaffResource::collection($certs),
            ], 200);

        }

        private function certSummary($query): array
        {
            $all = $query->get();
            $counts = $all->groupBy('status')->map->count();
            $expiringSoon = $all->filter(function ($cert) {
                if (!$cert->expires_at) {
                    return false;
                }
                return $cert->expires_at <= now()->addDays(30)->toDateString();
            })->count();

            return [
                'total' => $all->count(),
                'by_status' => [
                    'pending' => $counts->get('pending', 0),
                    'verified' => $counts->get('verified', 0),
                    'rejected' => $counts->get('rejected', 0),
                    'expired' => $counts->get('expired', 0),
                ],
                'expiring_soon_30d' => $expiringSoon,
            ];
        }

        private function latestVitalsTimestamp(int $wardId): ?string
        {
            $latest = collect([
                ward_temp::where('ward_id', $wardId)->max('updated_at'),
                ward_weight::where('ward_id', $wardId)->max('updated_at'),
                ward_sugar::where('ward_id', $wardId)->max('updated_at'),
                ward_bp_sys::where('ward_id', $wardId)->max('updated_at'),
                ward_bp_dia::where('ward_id', $wardId)->max('updated_at'),
                other_vitals::where('ward_id', $wardId)->max('updated_at'),
            ])->filter()->max();

            return $latest ? (string) $latest : null;
        }

        private function alertsCount(int $wardId): int
        {
            if (!Schema::hasTable('vital_alert_limits')) {
                return 0;
            }

            $limits = vital_alert_limit::where('ward_id', $wardId)->get()->keyBy('name');
            if ($limits->isEmpty()) {
                return 0;
            }

            $count = 0;

            $latestTemp = ward_temp::where('ward_id', $wardId)->latest()->first();
            if ($latestTemp && $limits->has('temperature')) {
                $count += $this->alertForValue($latestTemp->value, $limits->get('temperature'));
            }

            $latestWeight = ward_weight::where('ward_id', $wardId)->latest()->first();
            if ($latestWeight && $limits->has('weight')) {
                $count += $this->alertForValue($latestWeight->value, $limits->get('weight'));
            }

            $latestSugar = ward_sugar::where('ward_id', $wardId)->latest()->first();
            if ($latestSugar && $limits->has('sugar')) {
                $count += $this->alertForValue($latestSugar->value, $limits->get('sugar'));
            }

            $latestSys = ward_bp_sys::where('ward_id', $wardId)->latest()->first();
            if ($latestSys && $limits->has('bp_sys')) {
                $count += $this->alertForValue($latestSys->value, $limits->get('bp_sys'));
            }

            $latestDia = ward_bp_dia::where('ward_id', $wardId)->latest()->first();
            if ($latestDia && $limits->has('bp_dia')) {
                $count += $this->alertForValue($latestDia->value, $limits->get('bp_dia'));
            }

            return $count;
        }

        private function alertForValue($value, $limit): int
        {
            $count = 0;
            if ($limit->high_limit !== null && $value > $limit->high_limit) {
                $count++;
            }
            if ($limit->low_limit !== null && $value < $limit->low_limit) {
                $count++;
            }
            return $count;
        }

        public function carer_reviews(Request $request, $id){
            $auth = $this->requireCarerId($id);
            if ($auth instanceof \Illuminate\Http\Response) {
                return $auth;
            }

            $perPage = (int) $request->query('per_page', 20);
            $page = (int) $request->query('page', 1);

            $baseQuery = Review::where('carer_id', $id)->where('status', 'published');

            $summary = $this->buildReviewSummary($baseQuery);

            $reviews = $baseQuery
                ->with(['patient', 'consultation'])
                ->orderBy('updated_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            return response([
                'summary' => $summary,
                'reviews' => [
                    'data' => ReviewResource::collection($reviews->getCollection()),
                    'pagination' => [
                        'total' => $reviews->total(),
                        'page' => $reviews->currentPage(),
                        'per_page' => $reviews->perPage(),
                        'last_page' => $reviews->lastPage(),
                    ],
                ],
            ], 200);

        }

        private function buildReviewSummary($query): array
        {
            $all = $query->get();
            $count = $all->count();
            $avg = $count ? round($all->avg('rating'), 1) : 0.0;
            $recommendRate = $count ? round(($all->where('recomm', true)->count() / $count) * 100, 1) : 0.0;

            $breakdown = [
                '1' => $all->where('rating', '>=', 1)->where('rating', '<', 2)->count(),
                '2' => $all->where('rating', '>=', 2)->where('rating', '<', 3)->count(),
                '3' => $all->where('rating', '>=', 3)->where('rating', '<', 4)->count(),
                '4' => $all->where('rating', '>=', 4)->where('rating', '<', 5)->count(),
                '5' => $all->where('rating', '>=', 5)->count(),
            ];

            $last30 = $all->where('created_at', '>=', now()->subDays(30));
            $last30Count = $last30->count();
            $last30Avg = $last30Count ? round($last30->avg('rating'), 1) : 0.0;

            return [
                'average_rating' => $avg,
                'review_count' => $count,
                'recommend_rate_pct' => $recommendRate,
                'rating_breakdown' => $breakdown,
                'last_30d_count' => $last30Count,
                'last_30d_average_rating' => $last30Avg,
            ];
        }

}
