<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabTests\StoreLabTestRequest;
use App\Http\Requests\LabTests\UpdateLabTestRequest;
use App\Http\Resources\LabTestResource;
use App\Models\Consultation;
use App\Models\LabTest;
use App\Models\ward;
use App\Services\AccessService;
use App\Services\LabTestService;
use Illuminate\Http\Request;

class LabTestController extends Controller
{
    private AccessService $access;
    private LabTestService $labTests;

    public function __construct(AccessService $access, LabTestService $labTests)
    {
        $this->access = $access;
        $this->labTests = $labTests;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $status = $request->query('status');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $q = $request->query('q');
        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);

        $currentPatientId = $this->access->currentPatientId();
        if ($currentPatientId) {
            $consultationIds = Consultation::where('patient_id', $currentPatientId)->pluck('id');
            $wardIds = ward::where('patient_id', $currentPatientId)->pluck('id');
            $labtest = $this->queryByConsultationOrWard($consultationIds, $wardIds, $status, $dateFrom, $dateTo, $q, $perPage, $page);
            return response($labtest, 200);
        }

        $currentCarerId = $this->access->currentCarerId();
        if ($currentCarerId) {
            $consultationIds = Consultation::where('carer_id', $currentCarerId)->pluck('id');
            $wardIds = ward::where('carer_id', $currentCarerId)->pluck('id');
            $labtest = $this->queryByConsultationOrWard($consultationIds, $wardIds, $status, $dateFrom, $dateTo, $q, $perPage, $page);
            return response($labtest, 200);
        }

        $currentHospitalId = $this->access->currentHospitalId();
        if ($currentHospitalId) {
            $consultationIds = Consultation::where('hospital_id', $currentHospitalId)->pluck('id');
            $wardIds = ward::where('hospital_id', $currentHospitalId)->pluck('id');
            $labtest = $this->queryByConsultationOrWard($consultationIds, $wardIds, $status, $dateFrom, $dateTo, $q, $perPage, $page);
            return response($labtest, 200);
        }

        $labtest = collect();

        return response( LabTestResource::collection($labtest)
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
    public function store(StoreLabTestRequest $request)
    {
        $data = $request->validated();
        $labtest = $this->labTests->create($data, $this->access);

        return response(new LabTestResource($labtest), 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\LabTest  $labTest
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {

        $labTest=LabTest::find($id);
        if (is_null($labTest) && isset($labTest)) {
            return $this->sendError('LabTest not found.');
            }

        $this->authorize('view', $labTest);
        
            return response(new LabTestResource($labTest)
            , 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\LabTest  $labTest
     * @return \Illuminate\Http\Response
     */
    public function edit(LabTest $labTest)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\LabTest  $labTest
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateLabTestRequest $request, $id)
    {
        
        $data = $request->validated();
        $labTest=LabTest::find($id);
        if (!$labTest) {
            return $this->sendError('LabTest not found.');
        }
          
        $this->authorize('update', $labTest);

        $labTest = $this->labTests->update($labTest, $data, $this->access);
        
        
        return response(new LabTestResource($labTest), 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\LabTest  $labTest
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $labTest=LabTest::find( $id);
        if (!$labTest) {
            return $this->sendError('LabTest not found.');
        }

        $this->authorize('delete', $labTest);
        $labTest->delete();
   
        return response(['message' => 'Deleted']);
    }

    private function queryByConsultationOrWard($consultationIds, $wardIds, ?string $status, ?string $dateFrom, ?string $dateTo, ?string $q, int $perPage, int $page): array
    {
        if ($consultationIds->isEmpty() && $wardIds->isEmpty()) {
            return [
                'data' => [],
                'pagination' => [
                    'total' => 0,
                    'page' => $page,
                    'per_page' => $perPage,
                    'last_page' => 0,
                ],
            ];
        }

        $query = LabTest::where(function ($query) use ($consultationIds, $wardIds) {
            if ($consultationIds->isNotEmpty()) {
                $query->whereIn('consultation_id', $consultationIds);
            }
            if ($wardIds->isNotEmpty()) {
                $method = $consultationIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                $query->{$method}('ward_id', $wardIds);
            }
        });

        if ($status) {
            $query->where('status', $status);
        }
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
        if ($q) {
            $query->where('test_name', 'like', '%'.$q.'%');
        }

        $paginator = $query->orderBy('updated_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => LabTestResource::collection($paginator->getCollection()),
            'pagination' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }
}
