<?php

namespace App\Http\Controllers;

use App\Http\Resources\TestResource;
use App\Models\test;
use App\Models\TestPriceHistory;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TestController extends Controller
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
        $currentHospitalId = $this->access->currentHospitalId();
        if ($currentHospitalId) {
            $test = test::where('hospital_id', $currentHospitalId)->get();
            return response(TestResource::collection($test), 200);
        }

        $currentPatientId = $this->access->currentPatientId();
        if ($currentPatientId) {
            $test = test::where('is_active', 1)->get();
            return response(TestResource::collection($test), 200);
        }

        $test = collect();

        return response(TestResource::collection($test)
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
            'hospital_id' => 'required',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'sample_type' => 'nullable|string|max:255',
            'turnaround_time' => 'nullable|string|max:255',
            'preparation_notes' => 'nullable|string',
            'extra_notes' => 'nullable|string',
            'price' => 'required|integer|min:0',
            'is_active' => 'nullable|boolean',
            'status_reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response(['Validation errors' => $validator->errors()->all()], 422);
        }

        $currentHospitalId = $this->access->currentHospitalId();
        if ($currentHospitalId && (int) $data['hospital_id'] !== (int) $currentHospitalId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

      //  $test=test::create($data);

        $test= new test();

        $test->hospital_id=$data['hospital_id'];
        $test->name=$data['name'];
        $test->code=$data['code'] ?? null;
        $test->sample_type=$data['sample_type'] ?? null;
        $test->turnaround_time=$data['turnaround_time'] ?? null;
        $test->preparation_notes=$data['preparation_notes'] ?? null;
        $test->extra_notes=$data['extra_notes'] ?? null;
        $test->price=$data['price'];
        $test->is_active=array_key_exists('is_active', $data) ? (bool) $data['is_active'] : 1;
        $test->status_reason=$data['status_reason'] ?? null;

        $test->save();


        return response( new TestResource($test)
        , 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\test  $test
     * @return \Illuminate\Http\Response
     */
    public function show(test $test)
    {
        if (is_null($test)) {
            return $this->sendError('test not found.');
            }

        $this->authorize('view', $test);
        
            return response( new TestResource($test)
            , 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\test  $test
     * @return \Illuminate\Http\Response
     */
    public function edit(test $test)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\test  $test
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {

        $data=$request->all();
        
        $validator = Validator::make($request->all(), [
            'hospital_id' => 'required',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'sample_type' => 'nullable|string|max:255',
            'turnaround_time' => 'nullable|string|max:255',
            'preparation_notes' => 'nullable|string',
            'extra_notes' => 'nullable|string',
            'price' => 'required|integer|min:0',
            'is_active' => 'nullable|boolean',
            'status_reason' => 'nullable|string|max:255',
            'price_reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response(['Validation errors' => $validator->errors()->all()], 422);
        }
        
        $test_=test::find($id);
        if (!$test_) {
            return $this->sendError('test not found.');
        }

        $this->authorize('update', $test_);
        $previousPrice = $test_->price;
        $test_->hospital_id=$data['hospital_id'];
        $test_->name=$data['name'];
        $test_->code=$data['code'] ?? null;
        $test_->sample_type=$data['sample_type'] ?? null;
        $test_->turnaround_time=$data['turnaround_time'] ?? null;
        $test_->preparation_notes=$data['preparation_notes'] ?? null;
        $test_->price=$data['price'];
        $test_->extra_notes=$data['extra_notes'] ?? null;
        if (array_key_exists('is_active', $data)) {
            $test_->is_active = (bool) $data['is_active'];
        }
        $test_->status_reason=$data['status_reason'] ?? null;
        $test_->save();

        if ($previousPrice !== $test_->price) {
            TestPriceHistory::create([
                'test_id' => $test_->id,
                'hospital_id' => $test_->hospital_id,
                'previous_price' => $previousPrice,
                'price' => $test_->price,
                'changed_by' => auth()->id(),
                'reason' => $data['price_reason'] ?? null,
            ]);
        }

        return response(new TestResource($test_)
        , 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\test  $test
     * @return \Illuminate\Http\Response
     */
    public function destroy(test $test)
    {
        $this->authorize('delete', $test);
        $test->delete();
   
       return response(['message' => 'Deleted']);
    }
}
