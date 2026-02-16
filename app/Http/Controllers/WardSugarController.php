<?php

namespace App\Http\Controllers;

use App\Http\Resources\Other_VitalsResource;
use App\Models\Patient;
use App\Models\ward;
use App\Models\ward_sugar;
use App\Models\WardVitalAuditLog;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class WardSugarController extends Controller
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
            $sugar = ward_sugar::whereIn('ward_id', $wardIds)->get();
            return response(Other_VitalsResource::collection($sugar), 200);
        }

        $sugar = collect();

        return response( Other_VitalsResource::collection($sugar)
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
            'value' => 'required',
            'patient_id'=>'required',
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
    
        
            $sugar=new ward_sugar();
    
            
            $sugar->ward_id=$data['ward_id'];
            $sugar->value=$data['value'];
            $sugar->taken_at=$data['taken_at'] ?? null;
            $sugar->recorded_at=now();
            $sugar->source=$data['source'] ?? null;
            $sugar->save();

            // create and save timeline here
            $tme=new TimelineController;
            $tme->localTimelineStorage([
                'ward_id' => $data['ward_id'],
                'text' => 'Blood sugar recorded',
                'type' => 'sugar',
                'type_id' => $sugar->id,
                'meta' => [
                    'value' => $data['value'],
                    'unit' => 'mg/dL',
                    'taken_at' => $data['taken_at'] ?? null,
                    'recorded_at' => $sugar->recorded_at,
                    'source' => $data['source'] ?? null,
                ],
            ]);

            
            //save into patient sugar here
         
            $pat=Patient::find($data['patient_id']);
            $pat->sugar_level=$data['value'];
            $pat->save();

            $this->logVitalAudit($sugar->ward_id, 'sugar', $sugar->id, 'created', [
                'value' => $sugar->value,
                'unit' => 'mg/dL',
                'taken_at' => $sugar->taken_at,
                'recorded_at' => $sugar->recorded_at,
                'source' => $sugar->source,
            ]);
        
            
            return response(new Other_VitalsResource($sugar)
            , 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ward_sugar  $ward_sugar
     * @return \Illuminate\Http\Response
     */
    public function show(ward_sugar $ward_sugar)
    {
        if (is_null($ward_sugar)) {
            return $this->sendError('sugar not found.');
            }

        $deny = $this->denyWardAccessById($ward_sugar->ward_id);
        if ($deny) {
            return $deny;
        }
        
            return response( new Other_VitalsResource($ward_sugar)
            , 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ward_sugar  $ward_sugar
     * @return \Illuminate\Http\Response
     */
    public function edit(ward_sugar $ward_sugar)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ward_sugar  $ward_sugar
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $data=$request->all();
        
        $validator = Validator::make($request->all(), [
            'ward_id' => 'required',
            'value' => 'required',
            'patient_id'=>'required',
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
    
        
            $sugar= ward_sugar::find($id);
            if (is_null($sugar)) {
                return $this->sendError('sugar not found.');
            }
   
            $before = $sugar->getAttributes();
            $sugar->ward_id=$data['ward_id'];
            $sugar->value=$data['value'];
            $sugar->taken_at=$data['taken_at'] ?? $sugar->taken_at;
            $sugar->recorded_at=now();
            $sugar->source=$data['source'] ?? $sugar->source;
            $sugar->save();

            // create and save timeline here
            $tme=new TimelineController;
            $tme->localTimelineStorage([
                'ward_id' => $data['ward_id'],
                'text' => 'Blood sugar recorded',
                'type' => 'sugar',
                'type_id' => $sugar->id,
                'meta' => [
                    'value' => $data['value'],
                    'unit' => 'mg/dL',
                    'taken_at' => $data['taken_at'] ?? $sugar->taken_at,
                    'recorded_at' => $sugar->recorded_at,
                    'source' => $data['source'] ?? $sugar->source,
                ],
            ]);
            //save into patient sugar here
            $pat=Patient::find($data['patient_id']);
           $pat->sugar_level=$data['value'];
           $pat->save();

            $this->logVitalAudit($sugar->ward_id, 'sugar', $sugar->id, 'updated', [
                'from' => [
                    'value' => $before['value'] ?? null,
                    'taken_at' => $before['taken_at'] ?? null,
                    'source' => $before['source'] ?? null,
                ],
                'to' => [
                    'value' => $sugar->value,
                    'taken_at' => $sugar->taken_at,
                    'source' => $sugar->source,
                ],
            ]);
        
            
            return response(new Other_VitalsResource($sugar)
            , 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ward_sugar  $ward_sugar
     * @return \Illuminate\Http\Response
     */
    public function destroy(ward_sugar $ward_sugar)
    {
        $deny = $this->denyWardAccessById($ward_sugar->ward_id);
        if ($deny) {
            return $deny;
        }
        
        $this->logVitalAudit($ward_sugar->ward_id, 'sugar', $ward_sugar->id, 'deleted', [
            'value' => $ward_sugar->value,
            'taken_at' => $ward_sugar->taken_at,
            'source' => $ward_sugar->source,
        ]);
        $ward_sugar->delete();
   
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
