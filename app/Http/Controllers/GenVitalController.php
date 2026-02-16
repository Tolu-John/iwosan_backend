<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenVitals\StoreGenVitalRequest;
use App\Http\Requests\GenVitals\UpdateGenVitalRequest;
use App\Http\Resources\Gen_VitalResource;
use App\Models\Gen_Vital;
use App\Models\VitalAuditLog;
use App\Services\AccessService;
use App\Services\GenVitalService;
use Illuminate\Http\Request;

class GenVitalController extends Controller
{
    private AccessService $access;
    private GenVitalService $vitals;

    public function __construct(AccessService $access, GenVitalService $vitals)
    {
        $this->access = $access;
        $this->vitals = $vitals;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $patientIds = $this->access->accessiblePatientIds();
        if (!empty($patientIds)) {
            $other = Gen_Vital::whereIn('patient_id', $patientIds)->get();
            return response(Gen_VitalResource::collection($other), 200);
        }

        $other = collect();

        return response( Gen_VitalResource::collection($other)
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
    public function store(StoreGenVitalRequest $request)
    {
            $data = $request->validated();
            $vital = $this->vitals->create($data, $this->access);
        
            return response(new Gen_VitalResource($vital), 200);
        
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Gen_Vital  $gen_Vital
     * @return \Illuminate\Http\Response
     */
    public function show(Gen_Vital $gen_Vital)
    {
        if (is_null($gen_Vital)) {
            return $this->sendError('Vital not found.');
            }

        $this->authorize('view', $gen_Vital);
        
            return response( new Gen_VitalResource($gen_Vital)
            , 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Gen_Vital  $gen_Vital
     * @return \Illuminate\Http\Response
     */
    public function edit(Gen_Vital $gen_Vital)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Gen_Vital  $gen_Vital
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateGenVitalRequest $request, $id)
    {
        
            $data = $request->validated();
                $other= Gen_Vital::find($id);
                if (!$other) {
                    return $this->sendError('Vital not found.');
                }

                $this->authorize('view', $other);
        
                $other = $this->vitals->update($other, $data, $this->access);
                
                return response(new Gen_VitalResource($other), 200);
        
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Gen_Vital  $gen_Vital
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $gen_Vital=Gen_Vital::find( $id);
        if (!$gen_Vital) {
            return $this->sendError('Vital not found.');
        }

        $this->authorize('update', $gen_Vital);

        $this->vitals->delete($gen_Vital, $this->access);
   
        return response(['message' => 'Deleted']);
    
    }

    public function audit($id)
    {
        $vital = Gen_Vital::withTrashed()->find($id);
        if (!$vital) {
            return $this->sendError('Vital not found.');
        }

        $this->authorize('delete', $vital);

        $logs = VitalAuditLog::where('vital_id', $vital->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response([
            'vital_id' => (string) $vital->id,
            'audit' => $logs,
        ], 200);
    }

  
}
