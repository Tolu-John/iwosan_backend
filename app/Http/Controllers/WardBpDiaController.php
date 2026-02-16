<?php

namespace App\Http\Controllers;

use App\Http\Resources\Other_VitalsResource;
use App\Models\Patient;
use App\Models\ward;
use App\Models\ward_bp_dia;
use App\Models\WardVitalAuditLog;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class WardBpDiaController extends Controller
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
        $wardIds = $this->access->accessibleWardIds();
        if (!empty($wardIds)) {
            $bp = ward_bp_dia::whereIn('ward_id', $wardIds)->get();
            return response(Other_VitalsResource::collection($bp), 200);
        }

        $bp = collect();

        return response( Other_VitalsResource::collection($bp)
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
            'ward_id' => 'required',
            'patient_id'=>'required',
            'value' => 'required',
            'taken_at' => 'nullable|date',
            'source' => 'nullable|string|max:50',
            ]);
    
    
    
        if ($validator->fails()) {
            return response(['Validation errors' => $validator->errors()->all()], 422);
        }

        $deny = $this->denyWardAccessById($data['ward_id']);
        if ($deny) {
            return $deny;
        }
    
        
            $bp=new ward_bp_dia();
    
            
            $bp->ward_id=$data['ward_id'];
            $bp->value=$data['value'];
            $bp->taken_at=$data['taken_at'] ?? null;
            $bp->recorded_at=now();
            $bp->source=$data['source'] ?? null;
            $bp->save();

            // create and save timeline here
            $tme=new TimelineController;
            $tme->localTimelineStorage([
                'ward_id' => $data['ward_id'],
                'text' => 'Blood pressure diastolic recorded',
                'type' => 'bp_dia',
                'type_id' => $bp->id,
                'meta' => [
                    'value' => $data['value'],
                    'unit' => 'mmHg',
                    'taken_at' => $data['taken_at'] ?? null,
                    'recorded_at' => $bp->recorded_at,
                    'source' => $data['source'] ?? null,
                ],
            ]);
            //save into patient bp here
            $pat=Patient::find($data['patient_id']);
            $pat->bp_dia=$data['value'];
            $pat->save();

            $this->logVitalAudit($bp->ward_id, 'bp_dia', $bp->id, 'created', [
                'value' => $bp->value,
                'unit' => 'mmHg',
                'taken_at' => $bp->taken_at,
                'recorded_at' => $bp->recorded_at,
                'source' => $bp->source,
            ]);
        
            
            return response(new Other_VitalsResource($bp)
            , 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ward_bp_dia  $ward_bp_dia
     * @return \Illuminate\Http\Response
     */
    public function show(ward_bp_dia $ward_bp_dia)
    {
        if (is_null($ward_bp_dia)) {
            return $this->sendError('Blood pressure not found.');
            }

        $deny = $this->denyWardAccessById($ward_bp_dia->ward_id);
        if ($deny) {
            return $deny;
        }
        
            return response( new Other_VitalsResource($ward_bp_dia)
            , 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ward_bp_dia  $ward_bp_dia
     * @return \Illuminate\Http\Response
     */
    public function edit(ward_bp_dia $ward_bp_dia)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ward_bp_dia  $ward_bp_dia
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
 
        $data=$request->all();
        
        $validator = Validator::make($request->all(), [
            'ward_id' => 'required',
            'patient_id'=>'required',
            'value' => 'required',
            'taken_at' => 'nullable|date',
            'source' => 'nullable|string|max:50',
            ]);
    
    
    
            if ($validator->fails()) {
                return response(['Validation errors' => $validator->errors()->all()], 422);
            }

            $deny = $this->denyWardAccessById($data['ward_id']);
            if ($deny) {
                return $deny;
            }
    
        
            $bp= ward_bp_dia::find($id);
            if (is_null($bp)) {
                return $this->sendError('Blood pressure not found.');
            }
   
            $before = $bp->getAttributes();
            $bp->ward_id=$data['ward_id'];
            $bp->value=$data['value'];
            $bp->taken_at=$data['taken_at'] ?? $bp->taken_at;
            $bp->recorded_at=now();
            $bp->source=$data['source'] ?? $bp->source;
            $bp->save();

            // create and save timeline here
            $tme=new TimelineController;
            $tme->localTimelineStorage([
                'ward_id' => $data['ward_id'],
                'text' => 'Blood pressure diastolic recorded',
                'type' => 'bp_dia',
                'type_id' => $bp->id,
                'meta' => [
                    'value' => $data['value'],
                    'unit' => 'mmHg',
                    'taken_at' => $data['taken_at'] ?? $bp->taken_at,
                    'recorded_at' => $bp->recorded_at,
                    'source' => $data['source'] ?? $bp->source,
                ],
            ]);

            
            //save into patient bp here
            $pat=Patient::find($data['patient_id']);
             $pat->bp_dia=$data['value'];
             $pat->save();

            $this->logVitalAudit($bp->ward_id, 'bp_dia', $bp->id, 'updated', [
                'from' => [
                    'value' => $before['value'] ?? null,
                    'taken_at' => $before['taken_at'] ?? null,
                    'source' => $before['source'] ?? null,
                ],
                'to' => [
                    'value' => $bp->value,
                    'taken_at' => $bp->taken_at,
                    'source' => $bp->source,
                ],
            ]);
        
            
            return response(new Other_VitalsResource($bp)
            , 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ward_bp_dia  $ward_bp_dia
     * @return \Illuminate\Http\Response
     */
    public function destroy(ward_bp_dia $ward_bp_dia)
    {
        $deny = $this->denyWardAccessById($ward_bp_dia->ward_id);
        if ($deny) {
            return $deny;
        }

        $this->logVitalAudit($ward_bp_dia->ward_id, 'bp_dia', $ward_bp_dia->id, 'deleted', [
            'value' => $ward_bp_dia->value,
            'taken_at' => $ward_bp_dia->taken_at,
            'source' => $ward_bp_dia->source,
        ]);
        $ward_bp_dia->delete();
   
        return response(['message' => 'Deleted']);
    }

    private function denyWardAccessById($wardId)
    {
        $ward = ward::find($wardId);
        if (is_null($ward)) {
            return response()->json(['message' => 'Ward not found.'], 404);
        }

        return $this->access->denyIfFalse($this->access->canAccessWard($ward));
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
}
