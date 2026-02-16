<?php

namespace App\Http\Controllers;

use App\Http\Resources\DrugResource;
use App\Http\Resources\LabTestResource;
use App\Http\Resources\TimelineResource;
use App\Http\Resources\Other_VitalsResource;
use App\Http\Resources\Vital_Alert_LimitResource;
use App\Http\Resources\PatientLiteResource;
use App\Http\Resources\CarerLiteResource;
use App\Http\Resources\HospitalLiteResource;
use App\Models\Drug;
use App\Models\LabTest;
use App\Models\other_vitals;
use App\Models\timeline;
use App\Models\vital_alert_limit;
use App\Models\ward;
use App\Models\ward_bp;
use App\Models\ward_sugar;
use App\Models\ward_temp;
use App\Models\Carer;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\ward_bp_dia;
use App\Models\ward_bp_sys;
use App\Models\ward_weight;
use App\Models\WardVitalAuditLog;
use App\Services\AccessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

class ward_dashboard extends Controller
{
    private AccessService $access;

    public function __construct(AccessService $access)
    {
        $this->access = $access;
    }

    public function getPatientVitals(Request $request, $ward_id){

        $deny = $this->denyWardAccessById($ward_id);
        if ($deny) {
            return $deny;
        }

        $perPage = (int) $request->query('per_page', 50);
        $page = (int) $request->query('page', 1);

        return response($this->buildVitals($ward_id, $perPage, $page), 200);

    }


    public function UpdateWardVitals(Request $request,$ward_id){

        $deny = $this->denyWardAccessById($ward_id);
        if ($deny) {
            return $deny;
        }

       $data=$request->all();

        $patient_id=ward::select(['patient_id'])->where('id',$ward_id)->first();

        foreach ($data as $vital =>$x) {
            if ($vital === 'ward_id') {
                continue;
            }

            $value = is_array($x) ? ($x['value'] ?? null) : $x;
            $takenAt = is_array($x) ? ($x['taken_at'] ?? null) : null;
            $source = is_array($x) ? ($x['source'] ?? null) : null;
            $unit = is_array($x) ? ($x['unit'] ?? null) : null;
            $recordedAt = now();

            switch($vital){

                case 'temperature':

                    $vital_result=  new ward_temp();
                    $vital_result->ward_id=$ward_id;
                    $vital_result->value=$value;
                    $vital_result->taken_at=$takenAt;
                    $vital_result->recorded_at=$recordedAt;
                    $vital_result->source=$source;
                    $vital_result->save();

// create and save timeline here
            $tme=new TimelineController;
            $tme->localTimelineStorage([
                'ward_id' => $ward_id,
                'text' => 'Temperature recorded',
                'type' => 'temperature',
                'type_id' => $vital_result->id,
                'author_id' => Auth::id(),
                'author_role' => $this->currentActorRole(),
                'meta' => [
                    'value' => $value,
                    'unit' => $unit ?? 'C',
                    'taken_at' => $takenAt,
                    'recorded_at' => $recordedAt,
                    'source' => $source,
                ],
            ]);
            
                    $pat=Patient::find($patient_id['patient_id']);
                    $pat->temperature=$value;
                    $pat->save();

                    $this->logVitalAudit($ward_id, 'temperature', $vital_result->id, 'created', [
                        'value' => $value,
                        'unit' => $unit ?? 'C',
                        'taken_at' => $takenAt,
                        'recorded_at' => $recordedAt,
                        'source' => $source,
                    ]);

                    break;

                case 'weight':

                    $vital_result=  new ward_weight();
                    $vital_result->ward_id=$ward_id;
                    $vital_result->value=$value;
                    $vital_result->taken_at=$takenAt;
                    $vital_result->recorded_at=$recordedAt;
                    $vital_result->source=$source;
                    $vital_result->save();

                    
                    $tme=new TimelineController;
                    $tme->localTimelineStorage([
                        'ward_id' => $ward_id,
                        'text' => 'Weight recorded',
                        'type' => 'weight',
                        'type_id' => $vital_result->id,
                        'author_id' => Auth::id(),
                        'author_role' => $this->currentActorRole(),
                        'meta' => [
                            'value' => $value,
                            'unit' => $unit ?? 'kg',
                            'taken_at' => $takenAt,
                            'recorded_at' => $recordedAt,
                            'source' => $source,
                        ],
                    ]);


                    $pat=Patient::find($patient_id['patient_id']);
            $pat->weight=$value;
            $pat->save();

                    $this->logVitalAudit($ward_id, 'weight', $vital_result->id, 'created', [
                        'value' => $value,
                        'unit' => $unit ?? 'kg',
                        'taken_at' => $takenAt,
                        'recorded_at' => $recordedAt,
                        'source' => $source,
                    ]);
                    break;

               case 'Blood Pressure Diastolic':

                $vital_result=  new ward_bp_dia();
                $vital_result->ward_id=$ward_id;
                $vital_result->value=$value;
                $vital_result->taken_at=$takenAt;
                $vital_result->recorded_at=$recordedAt;
                $vital_result->source=$source;
                $vital_result->save();   


                $tme=new TimelineController;
                $tme->localTimelineStorage([
                    'ward_id' => $ward_id,
                    'text' => 'Blood pressure diastolic recorded',
                    'type' => 'bp_dia',
                    'type_id' => $vital_result->id,
                    'author_id' => Auth::id(),
                    'author_role' => $this->currentActorRole(),
                    'meta' => [
                        'value' => $value,
                        'unit' => $unit ?? 'mmHg',
                        'taken_at' => $takenAt,
                        'recorded_at' => $recordedAt,
                        'source' => $source,
                    ],
                ]);

                $pat=Patient::find($patient_id['patient_id']);
                $pat->bp_dia=$value;
                $pat->save();

                $this->logVitalAudit($ward_id, 'bp_dia', $vital_result->id, 'created', [
                    'value' => $value,
                    'unit' => $unit ?? 'mmHg',
                    'taken_at' => $takenAt,
                    'recorded_at' => $recordedAt,
                    'source' => $source,
                ]);

                      break;

                case 'Blood Pressure Systolic':

                        $vital_result=  new ward_bp_sys();
                        $vital_result->ward_id=$ward_id;
                        $vital_result->value=$value;
                        $vital_result->taken_at=$takenAt;
                        $vital_result->recorded_at=$recordedAt;
                        $vital_result->source=$source;
                        $vital_result->save();   
        
        
                        $tme=new TimelineController;
                        $tme->localTimelineStorage([
                            'ward_id' => $ward_id,
                            'text' => 'Blood pressure systolic recorded',
                            'type' => 'bp_sys',
                            'type_id' => $vital_result->id,
                            'author_id' => Auth::id(),
                            'author_role' => $this->currentActorRole(),
                            'meta' => [
                                'value' => $value,
                                'unit' => $unit ?? 'mmHg',
                                'taken_at' => $takenAt,
                                'recorded_at' => $recordedAt,
                                'source' => $source,
                            ],
                        ]);
        
                        $pat=Patient::find($patient_id['patient_id']);
                        $pat->bp_sys=$value;
                        $pat->save();

                        $this->logVitalAudit($ward_id, 'bp_sys', $vital_result->id, 'created', [
                            'value' => $value,
                            'unit' => $unit ?? 'mmHg',
                            'taken_at' => $takenAt,
                            'recorded_at' => $recordedAt,
                            'source' => $source,
                        ]);
        
                              break;
        

                case 'sugar_level':
                    
                    $vital_result=  new ward_sugar();
                    $vital_result->ward_id=$ward_id;
                    $vital_result->value=$value;
                    $vital_result->taken_at=$takenAt;
                    $vital_result->recorded_at=$recordedAt;
                    $vital_result->source=$source;
                    $vital_result->save(); 


                    $tme=new TimelineController;
                    $tme->localTimelineStorage([
                        'ward_id' => $ward_id,
                        'text' => 'Blood sugar recorded',
                        'type' => 'suagr_level',
                        'type_id' => $vital_result->id,
                        'author_id' => Auth::id(),
                        'author_role' => $this->currentActorRole(),
                        'meta' => [
                            'value' => $value,
                            'unit' => $unit ?? 'mg/dL',
                            'taken_at' => $takenAt,
                            'recorded_at' => $recordedAt,
                            'source' => $source,
                        ],
                    ]);


                    $pat=Patient::find($patient_id['patient_id']);
                    $pat->sugar_level=$value;
                    $pat->save();

                    $this->logVitalAudit($ward_id, 'sugar', $vital_result->id, 'created', [
                        'value' => $value,
                        'unit' => $unit ?? 'mg/dL',
                        'taken_at' => $takenAt,
                        'recorded_at' => $recordedAt,
                        'source' => $source,
                    ]);

                                break;

                 default:

                 $vital_result=  new other_vitals();
                 $vital_result->ward_id=$ward_id;
                 $vital_result->value=$value;
                 $vital_result->name=$vital;
                 $vital_result->unit=$unit;
                 $vital_result->taken_at=$takenAt;
                 $vital_result->recorded_at=$recordedAt;
                 $vital_result->source=$source;
                 $vital_result->save(); 


                 $tme=new TimelineController;
                 $tme->localTimelineStorage([
                     'ward_id' => $ward_id,
                     'text' => $vital.' recorded',
                     'type' => $vital,
                     'type_id' => $vital_result->id,
                     'author_id' => Auth::id(),
                     'author_role' => $this->currentActorRole(),
                     'meta' => [
                         'value' => $value,
                         'unit' => $unit,
                         'taken_at' => $takenAt,
                         'recorded_at' => $recordedAt,
                         'source' => $source,
                     ],
                 ]);

                 $this->logVitalAudit($ward_id, $vital, $vital_result->id, 'created', [
                     'value' => $value,
                     'unit' => $unit,
                     'taken_at' => $takenAt,
                     'recorded_at' => $recordedAt,
                     'source' => $source,
                 ]);

                 break;
            }
        }

        return response(
          
            [
                'message' => 'Updated Successfully', 
        ],
        200
        );

    }



    public function getWardTimeline(Request $request, $ward_id){

        $deny = $this->denyWardAccessById($ward_id);
        if ($deny) {
            return $deny;
        }

        $perPage = (int) $request->query('per_page', 50);
        $page = (int) $request->query('page', 1);

        $paginated = $this->paginateQuery(
            timeline::where('ward_id', $ward_id)->orderBy('updated_at', 'desc'),
            $perPage,
            $page
        );

        return [
            'data' => TimelineResource::collection($paginated['items']),
            'pagination' => $paginated['meta'],
        ];

    }



    public function getWardPrescriptions(Request $request, $ward_id){

        $deny = $this->denyWardAccessById($ward_id);
        if ($deny) {
            return $deny;
        }

        $type = $request->query('type');
        $status = $request->query('status');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $q = $request->query('q');
        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);

        $drugQuery = Drug::where('ward_id', $ward_id);
        $labQuery = LabTest::where('ward_id', $ward_id);

        if ($status) {
            $drugQuery->where('status', $status);
            $labQuery->where('status', $status);
        }
        if ($dateFrom) {
            $drugQuery->whereDate('start_date', '>=', $dateFrom);
            $labQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $drugQuery->whereDate('start_date', '<=', $dateTo);
            $labQuery->whereDate('created_at', '<=', $dateTo);
        }
        if ($q) {
            $drugQuery->where('name', 'like', '%'.$q.'%');
            $labQuery->where('test_name', 'like', '%'.$q.'%');
        }

        if ($type === 'drug') {
            $paginated = $this->paginateQuery($drugQuery->orderBy('updated_at', 'desc'), $perPage, $page);
            return response([
                'type' => 'drug',
                'data' => DrugResource::collection($paginated['items']),
                'pagination' => $paginated['meta'],
            ], 200);
        }

        if ($type === 'lab_test') {
            $paginated = $this->paginateQuery($labQuery->orderBy('updated_at', 'desc'), $perPage, $page);
            return response([
                'type' => 'lab_test',
                'data' => LabTestResource::collection($paginated['items']),
                'pagination' => $paginated['meta'],
            ], 200);
        }

        $drugs = $drugQuery->get();
        $labtests = $labQuery->get();

        $group = function ($items) {
            return [
                'active' => $items->where('status', 'active')->values(),
                'completed' => $items->where('status', 'completed')->values(),
                'discontinued' => $items->where('status', 'discontinued')->values(),
                'other' => $items->whereNotIn('status', ['active', 'completed', 'discontinued'])->values(),
            ];
        };

        $drugGroups = $group($drugs);
        $labGroups = [
            'ordered' => $labtests->where('status', 'ordered')->values(),
            'scheduled' => $labtests->where('status', 'scheduled')->values(),
            'collected' => $labtests->where('status', 'collected')->values(),
            'resulted' => $labtests->where('status', 'resulted')->values(),
            'other' => $labtests->whereNotIn('status', ['ordered', 'scheduled', 'collected', 'resulted'])->values(),
        ];

        return response([
            'drugs' => [
                'all' => DrugResource::collection($drugs),
                'by_status' => [
                    'active' => DrugResource::collection($drugGroups['active']),
                    'completed' => DrugResource::collection($drugGroups['completed']),
                    'discontinued' => DrugResource::collection($drugGroups['discontinued']),
                    'other' => DrugResource::collection($drugGroups['other']),
                ],
            ],
            'lab_tests' => [
                'all' => LabTestResource::collection($labtests),
                'by_status' => [
                    'ordered' => LabTestResource::collection($labGroups['ordered']),
                    'scheduled' => LabTestResource::collection($labGroups['scheduled']),
                    'collected' => LabTestResource::collection($labGroups['collected']),
                    'resulted' => LabTestResource::collection($labGroups['resulted']),
                    'other' => LabTestResource::collection($labGroups['other']),
                ],
            ],
        ], 200);


    }

    public function getWardDashboard(Request $request, $ward_id)
    {
        $deny = $this->denyWardAccessById($ward_id);
        if ($deny) {
            return $deny;
        }

        $ward = ward::find($ward_id);
        if (!$ward) {
            return response()->json(['message' => 'Ward not found.'], 404);
        }

        $patient = Patient::find($ward->patient_id);
        $carer = Carer::find($ward->carer_id);
        $hospital = Hospital::find($ward->hospital_id);

        $timelinePerPage = (int) $request->query('timeline_per_page', 50);
        $timelinePage = (int) $request->query('timeline_page', 1);

        $timelinePaginated = $this->paginateQuery(
            timeline::where('ward_id', $ward_id)->orderBy('updated_at', 'desc'),
            $timelinePerPage,
            $timelinePage
        );

        $drugs = Drug::where('ward_id', $ward_id)->get();
        $labtests = LabTest::where('ward_id', $ward_id)->get();

        $vitalsPerPage = (int) $request->query('vitals_per_page', 50);
        $vitalsPage = (int) $request->query('vitals_page', 1);
        $vitals = $this->buildVitals($ward_id, $vitalsPerPage, $vitalsPage);

        $alerts = $this->buildVitalAlerts($ward_id);

        return response([
            'ward' => [
                'id' => (string) $ward->id,
                'status' => $ward->discharged ? 'discharged' : 'active',
                'admission_date' => $ward->admission_date,
                'discharge_date' => $ward->discharge_date,
                'diagnosis' => $ward->diagnosis,
                'priority' => $ward->priority,
                'hospital_id' => $ward->hospital_id,
                'carer_id' => $ward->carer_id,
            ],
            'patient' => $patient ? new PatientLiteResource($patient) : null,
            'carer' => $carer ? new CarerLiteResource($carer) : null,
            'hospital' => $hospital ? new HospitalLiteResource($hospital) : null,
            'vitals' => $vitals,
            'timeline' => [
                'data' => TimelineResource::collection($timelinePaginated['items']),
                'pagination' => $timelinePaginated['meta'],
            ],
            'prescriptions' => [
                'drugs' => DrugResource::collection($drugs),
                'lab_tests' => LabTestResource::collection($labtests),
            ],
            'alerts' => $alerts,
            'updated_at' => Carbon::now()->toIso8601String(),
        ], 200);
    }

    private function denyWardAccessById($wardId)
    {
        $ward = ward::find($wardId);
        if (is_null($ward)) {
            return response()->json(['message' => 'Ward not found.'], 404);
        }

        return $this->access->denyIfFalse($this->access->canAccessWard($ward));
    }

    private function currentActorRole(): string
    {
        if ($this->access->currentCarerId()) {
            return 'carer';
        }
        if ($this->access->currentHospitalId()) {
            return 'hospital';
        }
        if ($this->access->currentPatientId()) {
            return 'patient';
        }

        return 'system';
    }

    private function buildVitalAlerts($wardId): array
    {
        $alerts = [];
        if (!Schema::hasTable('vital_alert_limits')) {
            return [];
        }

        $limits = vital_alert_limit::where('ward_id', $wardId)->get()->keyBy('name');

        $latestTemp = ward_temp::where('ward_id', $wardId)->latest()->first();
        if ($latestTemp && $limits->has('temperature')) {
            $limit = $limits->get('temperature');
            $alerts = array_merge($alerts, $this->compareAlert($latestTemp->value, $limit, 'temperature'));
        }

        $latestWeight = ward_weight::where('ward_id', $wardId)->latest()->first();
        if ($latestWeight && $limits->has('weight')) {
            $limit = $limits->get('weight');
            $alerts = array_merge($alerts, $this->compareAlert($latestWeight->value, $limit, 'weight'));
        }

        $latestSugar = ward_sugar::where('ward_id', $wardId)->latest()->first();
        if ($latestSugar && $limits->has('sugar')) {
            $limit = $limits->get('sugar');
            $alerts = array_merge($alerts, $this->compareAlert($latestSugar->value, $limit, 'sugar'));
        }

        $latestSys = ward_bp_sys::where('ward_id', $wardId)->latest()->first();
        if ($latestSys && $limits->has('bp_sys')) {
            $limit = $limits->get('bp_sys');
            $alerts = array_merge($alerts, $this->compareAlert($latestSys->value, $limit, 'bp_sys'));
        }

        $latestDia = ward_bp_dia::where('ward_id', $wardId)->latest()->first();
        if ($latestDia && $limits->has('bp_dia')) {
            $limit = $limits->get('bp_dia');
            $alerts = array_merge($alerts, $this->compareAlert($latestDia->value, $limit, 'bp_dia'));
        }

        return $alerts;
    }

    private function compareAlert($value, $limit, string $type): array
    {
        $alerts = [];

        if ($limit->high_limit !== null && $value > $limit->high_limit) {
            $alerts[] = [
                'type' => $type,
                'level' => 'high',
                'value' => $value,
                'threshold' => $limit->high_limit,
            ];
        }

        if ($limit->low_limit !== null && $value < $limit->low_limit) {
            $alerts[] = [
                'type' => $type,
                'level' => 'low',
                'value' => $value,
                'threshold' => $limit->low_limit,
            ];
        }

        return $alerts;
    }

    private function buildVitals($wardId, int $perPage, int $page): array
    {
        $result = [];

        $ward = ward::find($wardId);
        if (!$ward) {
            return $result;
        }

        $vitals = json_decode($ward['ward_vitals'], true) ?? [];

        foreach ($vitals as $vital) {
            $vitalResult = null;

            switch ($vital['name']) {
                case 'temperature':
                    $vitalResult = ward_temp::where('ward_id', $wardId)->orderBy('updated_at', 'desc');
                    break;
                case 'weight':
                    $vitalResult = ward_weight::where('ward_id', $wardId)->orderBy('updated_at', 'desc');
                    break;
                case 'Blood Pressure Diastolic':
                    $vitalResult = ward_bp_dia::where('ward_id', $wardId)->orderBy('updated_at', 'desc');
                    break;
                case 'Blood Pressure Systolic':
                    $vitalResult = ward_bp_sys::where('ward_id', $wardId)->orderBy('updated_at', 'desc');
                    break;
                case 'sugar':
                    $vitalResult = ward_sugar::where('ward_id', $wardId)->orderBy('updated_at', 'desc');
                    break;
                default:
                    $vitalResult = other_vitals::where('ward_id', $wardId)
                        ->where('name', $vital['name'])
                        ->orderBy('updated_at', 'desc');
                    break;
            }

            $paginated = $this->paginateQuery($vitalResult, $perPage, $page);
            $stats24 = $this->buildVitalStats((clone $vitalResult), now()->subHours(24));
            $stats7d = $this->buildVitalStats((clone $vitalResult), now()->subDays(7));

            $result[] = [
                'ward_id' => $wardId,
                'name' => $vital['name'],
                'vitalsList' => Other_VitalsResource::collection($paginated['items']),
                'stats' => [
                    'last_24h' => $stats24,
                    'last_7d' => $stats7d,
                ],
                'pagination' => $paginated['meta'],
            ];
        }

        return $result;
    }

    private function buildVitalStats($query, Carbon $from): array
    {
        $stats = $query->where('recorded_at', '>=', $from)
            ->selectRaw('COUNT(*) as count, MIN(value) as min, MAX(value) as max, AVG(value) as avg')
            ->first();

        return [
            'count' => (int) ($stats->count ?? 0),
            'min' => $stats->min !== null ? (float) $stats->min : null,
            'max' => $stats->max !== null ? (float) $stats->max : null,
            'avg' => $stats->avg !== null ? (float) $stats->avg : null,
        ];
    }

    private function paginateQuery($query, int $perPage, int $page): array
    {
        $perPage = $perPage > 0 ? $perPage : 50;
        $page = $page > 0 ? $page : 1;

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => collect($paginator->items()),
            'meta' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    public function vitalsAudit(Request $request, $ward_id)
    {
        $deny = $this->denyWardAccessById($ward_id);
        if ($deny) {
            return $deny;
        }

        $type = $request->query('type');
        $vitalId = $request->query('vital_id');
        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);

        $query = WardVitalAuditLog::where('ward_id', $ward_id)->orderBy('created_at', 'desc');
        if ($type) {
            $query->where('vital_type', $type);
        }
        if ($vitalId) {
            $query->where('vital_id', $vitalId);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return response([
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'results' => $paginator->items(),
        ], 200);
    }

    private function logVitalAudit(int $wardId, string $type, ?int $vitalId, string $action, array $changes): void
    {
        WardVitalAuditLog::create([
            'ward_id' => $wardId,
            'vital_type' => $type,
            'vital_id' => $vitalId,
            'action' => $action,
            'changes' => $changes,
            'created_by' => Auth::id(),
            'created_role' => $this->currentActorRole(),
        ]);
    }

    
}
