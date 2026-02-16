<?php

namespace App\Http\Controllers;

use App\Http\Resources\Other_VitalsResource;
use App\Models\Patient;
use App\Models\ward;
use App\Models\ward_temp;
use App\Models\WardVitalAuditLog;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class WardTempController extends Controller
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
            $temp = ward_temp::whereIn('ward_id', $wardIds)->get();
            return response(Other_VitalsResource::collection($temp), 200);
        }

        $temp = collect();

        return response( Other_VitalsResource::collection($temp)
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
    
        
            $temp=new ward_temp();
    
            
            $temp->ward_id=$data['ward_id'];
            $temp->value=$data['value'];
            $temp->taken_at=$data['taken_at'] ?? null;
            $temp->recorded_at=now();
            $temp->source=$data['source'] ?? null;
            $temp->save();

            // create and save timeline here
            $tme=new TimelineController;
            $tme->localTimelineStorage([
                'ward_id' => $data['ward_id'],
                'text' => 'Temperature recorded',
                'type' => 'temperature',
                'type_id' => $temp->id,
                'meta' => [
                    'value' => $data['value'],
                    'unit' => 'C',
                    'taken_at' => $data['taken_at'] ?? null,
                    'recorded_at' => $temp->recorded_at,
                    'source' => $data['source'] ?? null,
                ],
            ]);

            
            //save into patient temp here
            $pat=Patient::find($data['patient_id']);
           $pat->temperature=$data['value'];
           $pat->save();

            $this->logVitalAudit($temp->ward_id, 'temperature', $temp->id, 'created', [
                'value' => $temp->value,
                'unit' => 'C',
                'taken_at' => $temp->taken_at,
                'recorded_at' => $temp->recorded_at,
                'source' => $temp->source,
            ]);
        
            
            return response(new Other_VitalsResource($temp)
            , 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ward_temp  $ward_temp
     * @return \Illuminate\Http\Response
     */
    public function show(ward_temp $ward_temp)
    {
        if (is_null($ward_temp)) {
            return $this->sendError('Temperature not found.');
            }

        $deny = $this->denyWardAccessById($ward_temp->ward_id);
        if ($deny) {
            return $deny;
        }
        
            return response( new Other_VitalsResource($ward_temp)
            , 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ward_temp  $ward_temp
     * @return \Illuminate\Http\Response
     */
    public function edit(ward_temp $ward_temp)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ward_temp  $ward_temp
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
    
        
            $temp= ward_temp::find($id);
            if (is_null($temp)) {
                return $this->sendError('Temperature not found.');
            }

            $before = $temp->getAttributes();
            $temp->ward_id=$data['ward_id'];
            $temp->value=$data['value'];
            $temp->taken_at=$data['taken_at'] ?? $temp->taken_at;
            $temp->recorded_at=now();
            $temp->source=$data['source'] ?? $temp->source;
            $temp->save();

            // create and save timeline here
            $tme=new TimelineController;
            $tme->localTimelineStorage([
                'ward_id' => $data['ward_id'],
                'text' => 'Temperature recorded',
                'type' => 'temperature',
                'type_id' => $temp->id,
                'meta' => [
                    'value' => $data['value'],
                    'unit' => 'C',
                    'taken_at' => $data['taken_at'] ?? $temp->taken_at,
                    'recorded_at' => $temp->recorded_at,
                    'source' => $data['source'] ?? $temp->source,
                ],
            ]);
            //save into patient temp here
            $pat=Patient::find($data['patient_id']);
            $pat->temperature=$data['value'];
            $pat->save();

            $this->logVitalAudit($temp->ward_id, 'temperature', $temp->id, 'updated', [
                'from' => [
                    'value' => $before['value'] ?? null,
                    'taken_at' => $before['taken_at'] ?? null,
                    'source' => $before['source'] ?? null,
                ],
                'to' => [
                    'value' => $temp->value,
                    'taken_at' => $temp->taken_at,
                    'source' => $temp->source,
                ],
            ]);
        
            
            return response(new Other_VitalsResource($temp)
            , 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ward_temp  $ward_temp
     * @return \Illuminate\Http\Response
     */
    public function destroy(ward_temp $ward_temp)
    {
        $deny = $this->denyWardAccessById($ward_temp->ward_id);
        if ($deny) {
            return $deny;
        }

        $this->logVitalAudit($ward_temp->ward_id, 'temperature', $ward_temp->id, 'deleted', [
            'value' => $ward_temp->value,
            'taken_at' => $ward_temp->taken_at,
            'source' => $ward_temp->source,
        ]);
        $ward_temp->delete();
   
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
