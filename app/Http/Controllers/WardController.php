<?php

namespace App\Http\Controllers;

use App\Http\Resources\WardResource;
use App\Models\ward;
use App\Models\WardAuditLog;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class WardController extends Controller
{
    private AccessService $access;

    public function __construct(AccessService $access)
    {
        $this->access = $access;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $currentPatientId = $this->access->currentPatientId();
        if ($currentPatientId) {
            $query = ward::where('patient_id', $currentPatientId);
            return response($this->withFilters($query, request()), 200);
        }

        $currentCarerId = $this->access->currentCarerId();
        if ($currentCarerId) {
            $query = ward::where('carer_id', $currentCarerId);
            return response($this->withFilters($query, request()), 200);
        }

        $currentHospitalId = $this->access->currentHospitalId();
        if ($currentHospitalId) {
            $query = ward::where('hospital_id', $currentHospitalId);
            return response($this->withFilters($query, request()), 200);
        }

        $ward = collect();

        return response( WardResource::collection($ward)
        , 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data=$request->all();
        
        $validator = Validator::make($request->all(), [
        
            'hospital_id' => 'required|integer|exists:hospitals,id',
            'patient_id' => 'required|integer|exists:patients,id',
            'carer_id' => 'required|integer|exists:carers,id',
            'appt_id' => 'required|integer|exists:appointments,id',
            'diagnosis' => 'required|string',
            'admission_date' => 'required|date',
            'ward_vitals' => 'nullable|string',
            'priority' => 'required|string|max:50',
            'discharged' => 'nullable|boolean',
            'discharge_date' => 'nullable|date|required_if:discharged,1',
            'discharge_summary' => 'nullable|string',
            ]);
    
    
    
            if ($validator->fails()) {
                return response(['Validation errors' => $validator->errors()->all()], 422);
            }

            $currentPatientId = $this->access->currentPatientId();
            if ($currentPatientId && (int) $data['patient_id'] !== (int) $currentPatientId) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            $currentCarerId = $this->access->currentCarerId();
            if ($currentCarerId && (int) $data['carer_id'] !== (int) $currentCarerId) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            $currentHospitalId = $this->access->currentHospitalId();
            if ($currentHospitalId && (int) $data['hospital_id'] !== (int) $currentHospitalId) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
    
        
            $ward=new ward();
            
        
            $ward->hospital_id=$data['hospital_id'];
            $ward->patient_id=$data['patient_id'];
            $ward->carer_id=$data['carer_id'];
            $ward->appt_id=$data['appt_id'];
            $ward->diagnosis=$data['diagnosis'];
            $ward->admission_date=$data['admission_date'];
            $ward->ward_vitals=$data['ward_vitals'] ?? null;
            $ward->priority=$data['priority'];
            $ward->discharged = !empty($data['discharged']) ? 1 : 0;
            if (!empty($data['discharge_date'])) {
                $ward->discharge_date = $data['discharge_date'];
            }
            if (!empty($data['discharge_summary'])) {
                $ward->discharge_summary = $data['discharge_summary'];
            }
            $ward->save();

            $this->logAudit($ward, 'created', $ward->getAttributes());
           
        
            
            return response(new WardResource($ward)
            , 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ward  $ward
     * @return \Illuminate\Http\Response
     */
    public function show(ward $ward)
    {
        if (is_null($ward)) {
            return $this->sendError('Ward not found.');
            }

        $this->authorize('view', $ward);
        
            return response( new WardResource($ward)
            , 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ward  $ward
     * @return \Illuminate\Http\Response
     */
    public function edit(ward $ward)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ward  $ward
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request,$id)
    {
        $data=$request->all();
        
        $validator = Validator::make($request->all(), [
        
            'hospital_id' => 'required|integer|exists:hospitals,id',
            'patient_id' => 'required|integer|exists:patients,id',
            'carer_id' => 'required|integer|exists:carers,id',
            'admission_date' => 'required|date',
            'appt_id' => 'required|integer|exists:appointments,id',
            'diagnosis' => 'required|string',
            'ward_vitals' => 'nullable|string',
            'priority' => 'required|string|max:50',
            'discharged'=> 'required|boolean',
            'discharge_date' => 'nullable|date|required_if:discharged,1',
            'discharge_summary' => 'nullable|string',
        
            ]);
    
    
    
            if ($validator->fails()) {
                return response(['Validation errors' => $validator->errors()->all()], 422);
            }
    
        
            $ward=ward::find($id);
            if (!$ward) {
                return $this->sendError('Ward not found.');
            }

            $this->authorize('update', $ward);
    
            $before = $ward->getAttributes();
            $ward->hospital_id=$data['hospital_id'];
            $ward->patient_id=$data['patient_id'];
            $ward->carer_id=$data['carer_id'];
            $ward->appt_id=$data['appt_id'];
            $ward->diagnosis=$data['diagnosis'];
            $ward->admission_date=$data['admission_date'];
            $ward->discharged=$data['discharged'];
            if($data['discharged']== 1){
                $ward->discharge_date=$data['discharge_date'];
                if (!empty($data['discharge_summary'])) {
                    $ward->discharge_summary = $data['discharge_summary'];
                }
            }
            $ward->ward_vitals=$data['ward_vitals'] ?? null;
            $ward->priority=$data['priority'];
            $ward->save();
            $changes = $this->diffChanges($before, $ward->getAttributes());
            $this->logAudit($ward, 'updated', $changes);
        
            
            return response(new WardResource($ward)
            , 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ward  $ward
     * @return \Illuminate\Http\Response
     */
    public function destroy(ward $ward)
    {
        $this->authorize('delete', $ward);
        $this->logAudit($ward, 'deleted', $ward->getAttributes());
        $ward->delete();
   
        return response(['message' => 'Deleted']);
   
    }

    public function audit(Request $request, $id)
    {
        $ward = ward::find($id);
        if (!$ward) {
            return $this->sendError('Ward not found.');
        }

        $this->authorize('view', $ward);

        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);

        $query = WardAuditLog::where('ward_id', $ward->id)->orderBy('created_at', 'desc');
        $total = $query->count();
        $results = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response([
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'results' => $results,
        ], 200);
    }

    private function withFilters($query, Request $request): array
    {
        $status = $request->query('status', 'active');
        $priority = $request->query('priority');
        $carerId = $request->query('carer_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $q = $request->query('q');
        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);

        if ($status === 'active') {
            $query->where('discharged', 0);
        } elseif ($status === 'discharged') {
            $query->where('discharged', 1);
        }
        if ($priority) {
            $query->where('priority', $priority);
        }
        if ($carerId) {
            $query->where('carer_id', $carerId);
        }
        if ($dateFrom) {
            $query->whereDate('admission_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('admission_date', '<=', $dateTo);
        }
        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('diagnosis', 'like', '%'.$q.'%')
                    ->orWhereHas('patient.user', function ($user) use ($q) {
                        $user->where('firstname', 'like', '%'.$q.'%')
                            ->orWhere('lastname', 'like', '%'.$q.'%');
                    });
            });
        }

        $paginator = $query->orderBy('updated_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => WardResource::collection($paginator->getCollection()),
            'pagination' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    private function logAudit(ward $ward, string $action, ?array $changes): void
    {
        WardAuditLog::create([
            'ward_id' => $ward->id,
            'action' => $action,
            'changes' => $changes,
            'created_by' => Auth::id(),
            'created_role' => $this->currentActorRole(),
        ]);
    }

    private function diffChanges(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $key => $value) {
            if (!array_key_exists($key, $before)) {
                continue;
            }
            if ($before[$key] !== $value) {
                $changes[$key] = [
                    'from' => $before[$key],
                    'to' => $value,
                ];
            }
        }
        return $changes;
    }

    private function currentActorRole(): string
    {
        if ($this->access->currentPatientId()) {
            return 'patient';
        }
        if ($this->access->currentCarerId()) {
            return 'carer';
        }
        if ($this->access->currentHospitalId()) {
            return 'hospital';
        }

        return 'system';
    }
}
