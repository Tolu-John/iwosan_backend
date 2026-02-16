<?php

namespace App\Http\Controllers;

use App\Http\Resources\Other_VitalsResource;
use App\Models\other_vitals;
use App\Models\ward;
use App\Models\WardVitalAuditLog;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class OtherVitalsController extends Controller
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
            $other = other_vitals::whereIn('ward_id', $wardIds)->get();
            return response(Other_VitalsResource::collection($other), 200);
        }

        $other = collect();

        return response( Other_VitalsResource::collection($other)
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
            'name' => 'required',
            'unit' => 'nullable|string|max:20',
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
    
        
            $other=new other_vitals();
    
            
            $other->ward_id=$data['ward_id'];
            $other->value=$data['value'];
            $other->name=$data['name'];
            $other->unit=$data['unit'] ?? null;
            $other->taken_at=$data['taken_at'] ?? null;
            $other->recorded_at=now();
            $other->source=$data['source'] ?? null;
            $other->save();

            // create and save timeline here
            $tme=new TimelineController;
            $tme->localTimelineStorage([
                'ward_id' => $data['ward_id'],
                'text' => $data['name'].' recorded',
                'type' => $data['name'],
                'type_id' => $other->id,
                'meta' => [
                    'value' => $data['value'],
                    'unit' => $data['unit'] ?? null,
                    'taken_at' => $data['taken_at'] ?? null,
                    'recorded_at' => $other->recorded_at,
                    'source' => $data['source'] ?? null,
                ],
            ]);

            $this->logVitalAudit($other->ward_id, $other->name, $other->id, 'created', [
                'value' => $other->value,
                'unit' => $other->unit,
                'taken_at' => $other->taken_at,
                'recorded_at' => $other->recorded_at,
                'source' => $other->source,
            ]);
           
        
            
            return response(new Other_VitalsResource($other)
            , 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\other_vitals  $other_vitals
     * @return \Illuminate\Http\Response
     */
    public function show(other_vitals $other_vitals)
    {
        if (is_null($other_vitals)) {
            return $this->sendError('Vital not found.');
            }

        $deny = $this->denyWardAccessById($other_vitals->ward_id);
        if ($deny) {
            return $deny;
        }
        
            return response( new Other_VitalsResource($other_vitals)
            , 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\other_vitals  $other_vitals
     * @return \Illuminate\Http\Response
     */
    public function edit(other_vitals $other_vitals)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\other_vitals  $other_vitals
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $data=$request->all();
        
        $validator = Validator::make($request->all(), [
            'ward_id' => 'required',
            'value' => 'required',
            'name' => 'required',
            'unit' => 'nullable|string|max:20',
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
    
        
            $other= other_vitals::find($id);
            if (is_null($other)) {
                return $this->sendError('Vital not found.');
            }
   
            $before = $other->getAttributes();
            $other->ward_id=$data['ward_id'];
            $other->value=$data['value'];
            $other->name=$data['name'];
            $other->unit=$data['unit'] ?? $other->unit;
            $other->taken_at=$data['taken_at'] ?? $other->taken_at;
            $other->recorded_at=now();
            $other->source=$data['source'] ?? $other->source;
            $other->save();

            // create and save timeline here
            $tme=new TimelineController;
            $tme->localTimelineStorage([
                'ward_id' => $data['ward_id'],
                'text' => $data['name'].' recorded',
                'type' => $data['name'],
                'type_id' => $other->id,
                'meta' => [
                    'value' => $data['value'],
                    'unit' => $data['unit'] ?? $other->unit,
                    'taken_at' => $data['taken_at'] ?? $other->taken_at,
                    'recorded_at' => $other->recorded_at,
                    'source' => $data['source'] ?? $other->source,
                ],
            ]);

            $this->logVitalAudit($other->ward_id, $other->name, $other->id, 'updated', [
                'from' => [
                    'value' => $before['value'] ?? null,
                    'unit' => $before['unit'] ?? null,
                    'taken_at' => $before['taken_at'] ?? null,
                    'source' => $before['source'] ?? null,
                ],
                'to' => [
                    'value' => $other->value,
                    'unit' => $other->unit,
                    'taken_at' => $other->taken_at,
                    'source' => $other->source,
                ],
            ]);
           
        
            
            return response(new Other_VitalsResource($other)
            , 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\other_vitals  $other_vitals
     * @return \Illuminate\Http\Response
     */
    public function destroy(other_vitals $other_vitals)
    {
        $deny = $this->denyWardAccessById($other_vitals->ward_id);
        if ($deny) {
            return $deny;
        }

        $this->logVitalAudit($other_vitals->ward_id, $other_vitals->name, $other_vitals->id, 'deleted', [
            'value' => $other_vitals->value,
            'unit' => $other_vitals->unit,
            'taken_at' => $other_vitals->taken_at,
            'source' => $other_vitals->source,
        ]);
        $other_vitals->delete();
   
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
