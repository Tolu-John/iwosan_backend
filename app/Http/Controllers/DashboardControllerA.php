<?php

namespace App\Http\Controllers;

use App\Classes\AgoraDynamicKey\RtcTokenBuilder;
use App\Http\Resources\AppointmentResource;

use App\Http\Resources\CarerResource;
use App\Http\Resources\CarerApprovalLogResource;
use App\Http\Resources\CertliceStaffResource;
use App\Http\Resources\ComplaintResource;
use App\Http\Resources\ConsultLiteResource;
use App\Http\Resources\HospitalLiteResource;
use App\Http\Resources\HospitalPriceHistoryResource;
use App\Http\Resources\ReviewResource;
use App\Http\Resources\TeletestResource;
use App\Http\Resources\TestLiteResource;
use App\Http\Resources\WardLiteResource;
use App\Http\Resources\PatientLiteResource;
use App\Http\Resources\TestResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\WardResource;
use App\Models\Appointment;
use App\Models\Carer;
use App\Models\CarerApprovalLog;
use App\Models\Certlice;
use App\Models\Complaints;
use App\Models\Consultation;
use App\Models\Hospital;
use App\Models\HospitalPriceHistory;
use App\Models\Payment;
use App\Models\Patient;
use App\Models\Review;
use App\Models\Teletest;
use App\Models\test;
use App\Models\User;
use App\Models\ward;
use App\Models\other_vitals;
use App\Models\ward_bp_dia;
use App\Models\ward_bp_sys;
use App\Models\ward_sugar;
use App\Models\ward_temp;
use App\Models\ward_weight;
use App\Models\vital_alert_limit;
use App\Services\MetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class DashboardControllerA extends Controller
{
    private MetricsService $metricsService;

    public function __construct(MetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
    }

    private function requireHospitalId($id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $hospital = Hospital::where('user_id', $user->id)
            ->orWhere('firedb_id', $user->firedb_id)
            ->first();
        if (!$hospital || (int) $hospital->id !== (int) $id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $hospital;
    }

    private function requireHospitalUser()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $hospital = Hospital::where('user_id', $user->id)
            ->orWhere('firedb_id', $user->firedb_id)
            ->first();
        if (!$hospital) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $hospital;
    }
   
public function getalltests($id){
    $auth = $this->requireHospitalId($id);
    if ($auth instanceof \Illuminate\Http\Response) {
        return $auth;
    }

    $test=test::where('hospital_id',$id)
    ->orderBy('updated_at', 'desc')->get();

    return (response( TestResource::collection($test)
    , 200));


}



    public function getallhconsultation($id)
{
    $auth = $this->requireHospitalId($id);
    if ($auth instanceof \Illuminate\Http\Response) {
        return $auth;
    }

    $consultations=array();

$carers= Carer::where('hospital_id',$id)
->orderBy('updated_at', 'desc')->get();


foreach ($carers as $carer) {
    $consultation= Consultation::where('carer_id',$carer['id'])
   ->whereIn('treatment_type', ['Home visit', 'Home visit Admitted'])->orderBy('updated_at', 'desc')->get();


foreach($consultation as $consult){
array_push($consultations, new ConsultLiteResource($consult));
    
}



}


return response($consultations
    , 200);
}


public function getallvconsultation($id)
{
    $auth = $this->requireHospitalId($id);
    if ($auth instanceof \Illuminate\Http\Response) {
        return $auth;
    }

    $consultations=array();

$carers= Carer::where('hospital_id',$id)
->orderBy('updated_at', 'desc')->get();


foreach ($carers as $carer) {

    $consultation=Consultation::where('carer_id',$carer['id'])->where('treatment_type','Virtual visit')
->orderBy('updated_at', 'desc')->get();

foreach($consultation as $consult){
array_push($consultations, new ConsultLiteResource($consult));
    
}
}
return (response( $consultations
    , 200));
}


public function getallteletests($id){
    $auth = $this->requireHospitalId($id);
    if ($auth instanceof \Illuminate\Http\Response) {
        return $auth;
    }

    $results=array();

    $teletests=Teletest::where('hospital_id',$id)->get();

        return ( response( TeletestResource::collection($teletests), 200));
}



public function getallcarers(Request $request, $id){
    $auth = $this->requireHospitalId($id);
    if ($auth instanceof \Illuminate\Http\Response) {
        return $auth;
    }

    $status = $request->query('status', 'all');
    $position = $request->query('position');
    $q = $request->query('q');
    $sort = $request->query('sort', 'rating');
    $perPage = (int) $request->query('per_page', 20);
    $page = (int) $request->query('page', 1);

    $query = Carer::with('user')
        ->where('hospital_id', $id);

    if ($status === 'pending') {
        $query->where('admin_approved', 0);
    } elseif ($status === 'approved') {
        $query->where('admin_approved', 1)->where('super_admin_approved', 1);
    } elseif ($status === 'rejected') {
        $query->where('admin_approved', 0)->where('super_admin_approved', 0);
    }

    if ($position) {
        $query->where('position', 'like', '%'.$position.'%');
    }

    if ($q) {
        $query->whereHas('user', function ($sub) use ($q) {
            $sub->where('firstname', 'like', '%'.$q.'%')
                ->orWhere('lastname', 'like', '%'.$q.'%');
        })->orWhere('position', 'like', '%'.$q.'%');
    }

    if ($sort === 'recent') {
        $query->orderBy('updated_at', 'desc');
    } else {
        $query->orderBy('rating', 'desc');
    }

    $carers = $query->paginate($perPage, ['*'], 'page', $page);

    return response([
        'data' => CarerResource::collection($carers->getCollection()),
        'pagination' => [
            'total' => $carers->total(),
            'page' => $carers->currentPage(),
            'per_page' => $carers->perPage(),
            'last_page' => $carers->lastPage(),
        ],
    ], 200);

}



public function pendingappointments(Request $request, $id){
    $auth = $this->requireHospitalId($id);
    if ($auth instanceof \Illuminate\Http\Response) {
        return $auth;
    }

    $status = $request->query('status', 'pending_payment');
    $type = $request->query('appointment_type');
    $paymentStatus = $request->query('payment_status');
    $carerId = $request->query('carer_id');
    $dateFrom = $request->query('date_from');
    $dateTo = $request->query('date_to');
    $q = $request->query('q');
    $perPage = (int) $request->query('per_page', 20);
    $page = (int) $request->query('page', 1);

    $carerIds = Carer::where('hospital_id', $id)->pluck('id');

    $query = Appointment::with(['patient', 'carer', 'payments'])
        ->whereIn('carer_id', $carerIds)
        ->where('admin_approved', 0);

    if ($status) {
        $query->where('status', $status);
    }
    if ($type) {
        $query->where('appointment_type', $type);
    }
    if ($carerId) {
        $query->where('carer_id', $carerId);
    }
    if ($dateFrom) {
        $query->where('date_time', '>=', $dateFrom);
    }
    if ($dateTo) {
        $query->where('date_time', '<=', $dateTo);
    }
    if ($q) {
        $query->whereHas('patient.user', function ($sub) use ($q) {
            $sub->where('firstname', 'like', '%'.$q.'%')
                ->orWhere('lastname', 'like', '%'.$q.'%');
        });
    }
    if ($paymentStatus) {
        $query->whereHas('payments', function ($sub) use ($paymentStatus) {
            $sub->where('status', $paymentStatus);
        });
    }

    $appointments = $query->orderBy('updated_at', 'desc')
        ->paginate($perPage, ['*'], 'page', $page);

    $summary = [
        'total' => $appointments->total(),
        'pending_payment' => Appointment::whereIn('carer_id', $carerIds)->where('admin_approved', 0)->where('status', 'pending_payment')->count(),
        'scheduled' => Appointment::whereIn('carer_id', $carerIds)->where('admin_approved', 0)->where('status', 'scheduled')->count(),
    ];

    return response([
        'summary' => $summary,
        'data' => AppointmentResource::collection($appointments->getCollection()),
        'pagination' => [
            'total' => $appointments->total(),
            'page' => $appointments->currentPage(),
            'per_page' => $appointments->perPage(),
            'last_page' => $appointments->lastPage(),
        ],
    ]);
}


public function pendingcarers(Request $request, $id){
    $auth = $this->requireHospitalId($id);
    if ($auth instanceof \Illuminate\Http\Response) {
        return $auth;
    }
   
    $status = $request->query('status', 'pending');
    $q = $request->query('q');
    $perPage = (int) $request->query('per_page', 20);
    $page = (int) $request->query('page', 1);

    $query = Carer::with('user')->where('hospital_id', $id);

    if ($status === 'pending') {
        $query->where('admin_approved', 0);
    } elseif ($status === 'approved') {
        $query->where('admin_approved', 1)->where('super_admin_approved', 1);
    } elseif ($status === 'rejected') {
        $query->where('admin_approved', 0)->where('super_admin_approved', 0);
    }

    if ($q) {
        $query->whereHas('user', function ($sub) use ($q) {
            $sub->where('firstname', 'like', '%'.$q.'%')
                ->orWhere('lastname', 'like', '%'.$q.'%');
        })->orWhere('position', 'like', '%'.$q.'%');
    }

    $pending = $query->orderBy('updated_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

    $summary = [
        'total' => Carer::where('hospital_id', $id)->count(),
        'pending' => Carer::where('hospital_id', $id)->where('admin_approved', 0)->count(),
        'approved' => Carer::where('hospital_id', $id)->where('admin_approved', 1)->where('super_admin_approved', 1)->count(),
        'rejected' => Carer::where('hospital_id', $id)->where('admin_approved', 0)->where('super_admin_approved', 0)->count(),
    ];

    return response([
        'summary' => $summary,
        'data' => CarerResource::collection($pending->getCollection()),
        'pagination' => [
            'total' => $pending->total(),
            'page' => $pending->currentPage(),
            'per_page' => $pending->perPage(),
            'last_page' => $pending->lastPage(),
        ],
    ]);
}

public function reviewCarer(Request $request, $carerId)
{
    $hospital = $this->requireHospitalUser();
    if ($hospital instanceof \Illuminate\Http\Response) {
        return $hospital;
    }

    $validator = Validator::make($request->all(), [
        'status' => 'required|in:approved,rejected',
        'reason' => 'nullable|string|max:255',
    ]);

    if ($validator->fails()) {
        return response(['Validation errors' => $validator->errors()->all()], 422);
    }

    $carer = Carer::find($carerId);
    if (!$carer || (int) $carer->hospital_id !== (int) $hospital->id) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    $data = $validator->validated();

    if ($data['status'] === 'approved') {
        $carer->admin_approved = 1;
        $carer->super_admin_approved = 1;
    } else {
        $carer->admin_approved = 0;
        $carer->super_admin_approved = 0;
    }

    $carer->last_reviewed_at = now();
    $carer->save();

    CarerApprovalLog::create([
        'carer_id' => $carer->id,
        'hospital_id' => $hospital->id,
        'status' => $data['status'],
        'reason' => $data['reason'] ?? null,
        'reviewed_by' => Auth::id(),
    ]);

    return response(new CarerResource($carer), 200);
}

public function carerApprovalHistory(Request $request, $carerId)
{
    $hospital = $this->requireHospitalUser();
    if ($hospital instanceof \Illuminate\Http\Response) {
        return $hospital;
    }

    $carer = Carer::find($carerId);
    if (!$carer || (int) $carer->hospital_id !== (int) $hospital->id) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    $perPage = (int) $request->query('per_page', 20);
    $page = (int) $request->query('page', 1);

    $query = CarerApprovalLog::where('carer_id', $carerId)
        ->where('hospital_id', $hospital->id)
        ->orderBy('created_at', 'desc');

    $total = $query->count();
    $results = $query->skip(($page - 1) * $perPage)
        ->take($perPage)
        ->get();

    return response([
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'results' => CarerApprovalLogResource::collection($results),
    ], 200);
}

public function getcomplaints($id){
    $auth = $this->requireHospitalId($id);
    if ($auth instanceof \Illuminate\Http\Response) {
        return $auth;
    }

    $complaints=Complaints::where('hospital_id',$id)->orderBy('created_at', 'desc')->get();

    return (response( ComplaintResource::collection($complaints)
    , 200));

}

public function HospitalLite($id){
    $auth = $this->requireHospitalId($id);
    if ($auth instanceof \Illuminate\Http\Response) {
        return $auth;
    }

    $hospital= Hospital::find($id);

    return (response(new HospitalLiteResource($hospital)));

}


public function UpdateHospitalPrices(Request $request, $id){
    $auth = $this->requireHospitalId($id);
    if ($auth instanceof \Illuminate\Http\Response) {
        return $auth;
    }

    $validator = Validator::make($request->all(), [
        'home_visit_price' => 'required|integer|min:0',
        'virtual_visit_price' => 'required|integer|min:0',
        'virtual_ward_price' => 'required|integer|min:0',
        'reason' => 'nullable|string|max:255',
    ]);

    if ($validator->fails()) {
        return response(['Validation errors' => $validator->errors()->all()], 422);
    }

    $data = $validator->validated();
    $hospital = Hospital::find($id);
    if (!$hospital) {
        return response(['message' => 'Hospital not found'], 404);
    }

    $previous = [
        'home_visit_price' => $hospital->home_visit_price,
        'virtual_visit_price' => $hospital->virtual_visit_price,
        'virtual_ward_price' => $hospital->virtual_ward_price,
    ];

    $hospital->home_visit_price = $data['home_visit_price'];
    $hospital->virtual_visit_price = $data['virtual_visit_price'];
    $hospital->virtual_ward_price = $data['virtual_ward_price'];
    $hospital->save();

    $changed = $previous['home_visit_price'] !== $hospital->home_visit_price
        || $previous['virtual_visit_price'] !== $hospital->virtual_visit_price
        || $previous['virtual_ward_price'] !== $hospital->virtual_ward_price;

    if ($changed) {
        HospitalPriceHistory::create([
            'hospital_id' => $hospital->id,
            'previous_home_visit_price' => $previous['home_visit_price'],
            'previous_virtual_visit_price' => $previous['virtual_visit_price'],
            'previous_virtual_ward_price' => $previous['virtual_ward_price'],
            'home_visit_price' => $hospital->home_visit_price,
            'virtual_visit_price' => $hospital->virtual_visit_price,
            'virtual_ward_price' => $hospital->virtual_ward_price,
            'changed_by' => Auth::id(),
            'reason' => $data['reason'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    return response(new HospitalLiteResource($hospital));

}

public function getHospitalPriceHistory(Request $request, $id)
{
    $auth = $this->requireHospitalId($id);
    if ($auth instanceof \Illuminate\Http\Response) {
        return $auth;
    }

    $perPage = (int) $request->query('per_page', 20);
    $page = (int) $request->query('page', 1);

    $query = HospitalPriceHistory::where('hospital_id', $id)
        ->orderBy('created_at', 'desc');

    $total = $query->count();
    $results = $query->skip(($page - 1) * $perPage)
        ->take($perPage)
        ->get();

    return response([
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'results' => HospitalPriceHistoryResource::collection($results),
    ], 200);
}


public function getwardAdmissions(Request $request, $id){
    $auth = $this->requireHospitalId($id);
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
        ->where('hospital_id', $id);

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




public function carermetrics($id){
    $hospital = $this->requireHospitalUser();
    if ($hospital instanceof \Illuminate\Http\Response) {
        return $hospital;
    }

    $validator = Validator::make(request()->all(), [
        'from' => 'nullable|date',
        'to' => 'nullable|date',
        'tz' => 'nullable|string|max:60',
    ]);

    if ($validator->fails()) {
        return response(['Validation errors' => $validator->errors()->all()], 422);
    }

    $carer = Carer::find($id);
    if (!$carer || (int) $carer->hospital_id !== (int) $hospital->id) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    $data = $validator->validated();

    return $this->metricsService->carerMetrics(
        $id,
        $data['from'] ?? null,
        $data['to'] ?? null,
        $data['tz'] ?? null
    );

}



public function hospitalmetrics($id){
    $auth = $this->requireHospitalId($id);
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

    return $this->metricsService->hospitalMetrics(
        $id,
        $data['from'] ?? null,
        $data['to'] ?? null,
        $data['tz'] ?? null
    );

}


public function hospital_certs(Request $request, $id){
    $auth = $this->requireHospitalId($id);
    if ($auth instanceof \Illuminate\Http\Response) {
        return $auth;
    }

    $status = $request->query('status');
    $ownerType = $request->query('type', 'hospital');

    if ($ownerType === 'carer') {
        $carerIds = Carer::where('hospital_id', $id)->pluck('id');
        $certsQuery = Certlice::where('type', 'carer')->whereIn('type_id', $carerIds);
    } else {
        $certsQuery = Certlice::where('type', 'hospital')->where('type_id', $id);
    }

    if ($status) {
        $certsQuery->where('status', $status);
    }

    $certs = $certsQuery->orderBy('updated_at', 'desc')->get();

    $summary = $this->certSummary($certsQuery->clone());

    return response([
        'summary' => $summary,
        'certificates' => CertliceStaffResource::collection($certs),
    ]);

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


public function token(Request $request)
{
        
    $validator = Validator::make($request->all(), [
            'channelName' => 'required|string|max:255',
            'uid' => 'required',
            'ttl' => 'nullable|integer|min:60|max:86400',
        ]);

        if ($validator->fails()) {
            return response(['Validation errors' => $validator->errors()->all()], 422);
        }

    $appID = env('AGORA_APP_ID');
    $appCertificate = env('AGORA_APP_CERTIFICATE');
    if (!$appID || !$appCertificate) {
        return response()->json(['message' => 'Agora credentials not configured.'], 500);
    }

    $channelName = (string) $request->channelName;
    $uId = $request->uid;
    $role = RtcTokenBuilder::RoleAttendee;
    $expireTimeInSeconds = (int) ($request->ttl ?? 3600);
    $currentTimestamp = now()->getTimestamp();
    $privilegeExpiredTs = $currentTimestamp + $expireTimeInSeconds;

    $token = RtcTokenBuilder::buildTokenWithUid($appID, $appCertificate, $channelName, $uId, $role, $privilegeExpiredTs);


    

    return response()->json([
        'message' => 'Generated Successfully',
        'id' => null,
        "expires_at"=> $privilegeExpiredTs,
        "access_token"=>$token
    ], 200);

}



  public function deletefile($location,$url,$named_extension){

           $url_bits = explode('/', $url);
           
           $file_loc = $url_bits[(sizeof($url_bits)-1)];

           if (Storage::disk("iwosan_files")->exists($location.$file_loc) && ($file_loc != $named_extension)) {
               
            Storage::disk("iwosan_files")->delete($location.$file_loc);
            
        }


    }



}
