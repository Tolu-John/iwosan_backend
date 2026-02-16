<?php

namespace App\Http\Controllers;

use App\Http\Requests\Appointments\StoreAppointmentRequest;
use App\Http\Requests\Appointments\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Services\AccessService;
use App\Services\AppointmentService;

class AppointmentController extends Controller
{
    private AccessService $access;
    private AppointmentService $appointments;

    public function __construct(AccessService $access, AppointmentService $appointments)
    {
        $this->access = $access;
        $this->appointments = $appointments;
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
            $appointment = Appointment::where('patient_id', $currentPatientId)->get();
            return response(AppointmentResource::collection($appointment), 200);
        }

        $currentCarerId = $this->access->currentCarerId();
        if ($currentCarerId) {
            $appointment = Appointment::where('carer_id', $currentCarerId)->get();
            return response(AppointmentResource::collection($appointment), 200);
        }

        $currentHospitalId = $this->access->currentHospitalId();
        if ($currentHospitalId) {
            $carerIds = \App\Models\Carer::where('hospital_id', $currentHospitalId)->pluck('id');
            $appointment = Appointment::whereIn('carer_id', $carerIds)->get();
            return response(AppointmentResource::collection($appointment), 200);
        }

        $appointment = collect();
        return response(AppointmentResource::collection($appointment)
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
    public function store(StoreAppointmentRequest $request)
    {
        $data = $request->validated();
        $appointment = $this->appointments->create($data, $this->access);

        return response(new AppointmentResource($appointment), 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $appointment=Appointment::find( $id);
        if (is_null($appointment)) {
            return $this->sendError('Appointment not found.');
            }

        $this->authorize('view', $appointment);
        
            return response( new AppointmentResource($appointment)
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
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateAppointmentRequest $request, $id)
    {
        $appointment = Appointment::find($id);
        if (!$appointment) {
            return $this->sendError('Appointment not found.');
        }

        $this->authorize('update', $appointment);

        $data = $request->validated();
        $appointment = $this->appointments->update($appointment, $data, $this->access);

        return response(new AppointmentResource($appointment), 200);
    
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $appointment=Appointment::find( $id);
        if (!$appointment) {
            return $this->sendError('Appointment not found.');
        }

        $this->authorize('delete', $appointment);
        $appointment->delete();
   
        return response(['message' => 'Deleted']);
    }
}
