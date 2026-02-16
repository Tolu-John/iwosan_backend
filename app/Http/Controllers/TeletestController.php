<?php

namespace App\Http\Controllers;

use App\Http\Requests\Teletests\StoreTeletestRequest;
use App\Http\Requests\Teletests\UpdateTeletestRequest;
use App\Http\Resources\TeletestResource;
use App\Models\Teletest;
use App\Services\AccessService;
use App\Services\TeletestService;

class TeletestController extends Controller
{
    private AccessService $access;
    private TeletestService $teletests;

    public function __construct(AccessService $access, TeletestService $teletests)
    {
        $this->access = $access;
        $this->teletests = $teletests;
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
            $teletest = Teletest::where('patient_id', $currentPatientId)->get();
            return response(TeletestResource::collection($teletest), 200);
        }

        $currentCarerId = $this->access->currentCarerId();
        if ($currentCarerId) {
            $teletest = Teletest::where('carer_id', $currentCarerId)->get();
            return response(TeletestResource::collection($teletest), 200);
        }

        $currentHospitalId = $this->access->currentHospitalId();
        if ($currentHospitalId) {
            $teletest = Teletest::where('hospital_id', $currentHospitalId)->get();
            return response(TeletestResource::collection($teletest), 200);
        }

        $teletest = collect();

        return response(TeletestResource::collection($teletest)
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
    public function store(StoreTeletestRequest $request)
    {
        $data = $request->validated();
        $teletest = $this->teletests->create($data, $this->access);

        return response(new TeletestResource($teletest), 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Teletest  $teletest
     * @return \Illuminate\Http\Response
     */
    public function show(Teletest $teletest)
    {
        
        if (is_null($teletest)) {
            return $this->sendError('teletest not found.');
            }

        $this->authorize('view', $teletest);
        
            return response(new TeletestResource($teletest)
            , 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Teletest  $teletest
     * @return \Illuminate\Http\Response
     */
    public function edit(Teletest $teletest)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Teletest  $teletest
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateTeletestRequest $request, $id)
    {
        $teletest=Teletest::find($id);
        if (!$teletest) {
            return $this->sendError('teletest not found.');
        }

        $this->authorize('update', $teletest);
        $data = $request->validated();
        $teletest = $this->teletests->update($teletest, $data, $this->access);

        return response(new TeletestResource($teletest), 200);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Teletest  $teletest
     * @return \Illuminate\Http\Response
     */
    public function destroy(Teletest $teletest)
    {
          $this->authorize('delete', $teletest);
          $teletest->delete();
   
       return response(['message' => 'Deleted']);
   
    }
}
