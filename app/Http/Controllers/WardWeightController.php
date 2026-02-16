<?php

namespace App\Http\Controllers;

use App\Http\Resources\Other_VitalsResource;
use App\Models\Patient;
use App\Models\ward;
use App\Models\ward_weight;
use App\Models\WardVitalAuditLog;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class WardWeightController extends Controller
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
            $weight = ward_weight::whereIn('ward_id', $wardIds)->get();
            return response(Other_VitalsResource::collection($weight), 200);
        }

        $weight = collect();

        return response( Other_VitalsResource::collection($weight)
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
    
        
            $weight=new ward_weight();
    
            
            $weight->ward_id=$data['ward_id'];
            $weight->value=$data['value'];
            $weight->taken_at=$data['taken_at'] ?? null;
            $weight->recorded_at=now();
            $weight->source=$data['source'] ?? null;
            $weight->save();

            // create and save timeline here
            $tme=new TimelineController;
            $tme->localTimelineStorage([
                'ward_id' => $data['ward_id'],
                'text' => 'Weight recorded',
                'type' => 'weight',
                'type_id' => $weight->id,
                'meta' => [
                    'value' => $data['value'],
                    'unit' => 'kg',
                    'taken_at' => $data['taken_at'] ?? null,
                    'recorded_at' => $weight->recorded_at,
                    'source' => $data['source'] ?? null,
                ],
            ]);
            //save into patient weight here
            $pat=Patient::find($data['patient_id']);
            $pat->weight=$data['value'];
            $pat->save();

            $this->logVitalAudit($weight->ward_id, 'weight', $weight->id, 'created', [
                'value' => $weight->value,
                'unit' => 'kg',
                'taken_at' => $weight->taken_at,
                'recorded_at' => $weight->recorded_at,
                'source' => $weight->source,
            ]);
        
            
            return response(new Other_VitalsResource($weight)
            , 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ward_weight  $ward_weight
     * @return \Illuminate\Http\Response
     */
    public function show(ward_weight $ward_weight)
    {
        if (is_null($ward_weight)) {
            return $this->sendError('weighterature not found.');
            }

        $deny = $this->denyWardAccessById($ward_weight->ward_id);
        if ($deny) {
            return $deny;
        }
        
            return response( new Other_VitalsResource($ward_weight)
            , 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ward_weight  $ward_weight
     * @return \Illuminate\Http\Response
     */
    public function edit(ward_weight $ward_weight)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ward_weight  $ward_weight
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
    
        
            $weight= ward_weight::find($id);
            if (is_null($weight)) {
                return $this->sendError('weighterature not found.');
            }
   
            $before = $weight->getAttributes();
            $weight->ward_id=$data['ward_id'];
            $weight->value=$data['value'];
            $weight->taken_at=$data['taken_at'] ?? $weight->taken_at;
            $weight->recorded_at=now();
            $weight->source=$data['source'] ?? $weight->source;
            $weight->save();

            // create and save timeline here
            $tme=new TimelineController;
            $tme->localTimelineStorage([
                'ward_id' => $data['ward_id'],
                'text' => 'Weight recorded',
                'type' => 'weight',
                'type_id' => $weight->id,
                'meta' => [
                    'value' => $data['value'],
                    'unit' => 'kg',
                    'taken_at' => $data['taken_at'] ?? $weight->taken_at,
                    'recorded_at' => $weight->recorded_at,
                    'source' => $data['source'] ?? $weight->source,
                ],
            ]);

            //save into patient weight here
           $pat=Patient::find($data['patient_id']);
           $pat->weight=$data['value'];
           $pat->save();

            $this->logVitalAudit($weight->ward_id, 'weight', $weight->id, 'updated', [
                'from' => [
                    'value' => $before['value'] ?? null,
                    'taken_at' => $before['taken_at'] ?? null,
                    'source' => $before['source'] ?? null,
                ],
                'to' => [
                    'value' => $weight->value,
                    'taken_at' => $weight->taken_at,
                    'source' => $weight->source,
                ],
            ]);
        
            
            return response(new Other_VitalsResource($weight)
            , 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ward_weight  $ward_weight
     * @return \Illuminate\Http\Response
     */
    public function destroy(ward_weight $ward_weight)
    {
        $deny = $this->denyWardAccessById($ward_weight->ward_id);
        if ($deny) {
            return $deny;
        }

        $this->logVitalAudit($ward_weight->ward_id, 'weight', $ward_weight->id, 'deleted', [
            'value' => $ward_weight->value,
            'taken_at' => $ward_weight->taken_at,
            'source' => $ward_weight->source,
        ]);
        $ward_weight->delete();
   
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
