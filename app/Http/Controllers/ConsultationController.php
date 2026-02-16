<?php

namespace App\Http\Controllers;

use App\Http\Requests\Consultations\StoreConsultationRequest;
use App\Http\Requests\Consultations\UpdateConsultationRequest;
use App\Http\Resources\ConsultationResource;
use App\Models\Consultation;
use App\Services\AccessService;
use App\Services\ConsultationService;

class ConsultationController extends Controller
{
    private AccessService $access;
    private ConsultationService $consultations;

    public function __construct(AccessService $access, ConsultationService $consultations)
    {
        $this->access = $access;
        $this->consultations = $consultations;
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
            $Consultation = Consultation::where('patient_id', $currentPatientId)->get();
            return response(ConsultationResource::collection($Consultation), 200);
        }

        $currentCarerId = $this->access->currentCarerId();
        if ($currentCarerId) {
            $Consultation = Consultation::where('carer_id', $currentCarerId)->get();
            return response(ConsultationResource::collection($Consultation), 200);
        }

        $currentHospitalId = $this->access->currentHospitalId();
        if ($currentHospitalId) {
            $Consultation = Consultation::where('hospital_id', $currentHospitalId)->get();
            return response(ConsultationResource::collection($Consultation), 200);
        }

        $Consultation = collect();

        return response(ConsultationResource::collection($Consultation)
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
    public function store(StoreConsultationRequest $request)
    {
        $data = $request->validated();
        $consultation = $this->consultations->create($data, $this->access);

        return response(new ConsultationResource($consultation), 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $Consultation=Consultation::find($id);
        
        if (is_null($Consultation)) {
            return $this->sendError('Consultation not found.');
            }

        $this->authorize('view', $Consultation);
            
        
            return response( new ConsultationResource($Consultation)
            , 200);
    
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateConsultationRequest $request, $id)
    {
        $consultation = Consultation::find($id);
        if (!$consultation) {
            return $this->sendError('Consultation not found.');
        }

        $this->authorize('update', $consultation);

        $data = $request->validated();
        $consultation = $this->consultations->update($consultation, $data, $this->access);

        return response(new ConsultationResource($consultation), 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $Consultation=Consultation::find( $id);
        if (!$Consultation) {
            return $this->sendError('Consultation not found.');
        }

        $this->authorize('delete', $Consultation);
        $Consultation->delete();
   
        return response(['message' => 'Deleted']);
    
    }
}
