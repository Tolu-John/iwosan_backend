<?php

namespace App\Http\Controllers;

use App\Http\Requests\Drugs\StoreDrugRequest;
use App\Http\Requests\Drugs\UpdateDrugRequest;
use App\Http\Resources\DrugResource;
use App\Models\Consultation;
use App\Models\Drug;
use App\Models\ward;
use App\Services\AccessService;
use App\Services\DrugService;
use Illuminate\Http\Request;

class DrugController extends Controller
{
    private AccessService $access;
    private DrugService $drugs;

    public function __construct(AccessService $access, DrugService $drugs)
    {
        $this->access = $access;
        $this->drugs = $drugs;
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
            $drug = $this->queryByConsultationOrWard($consultationIds, $wardIds, $status, $dateFrom, $dateTo, $q, $perPage, $page);
            return response()->json($drug, 200);
        }

        $currentCarerId = $this->access->currentCarerId();
        if ($currentCarerId) {
            $consultationIds = Consultation::where('carer_id', $currentCarerId)->pluck('id');
            $wardIds = ward::where('carer_id', $currentCarerId)->pluck('id');
            $drug = $this->queryByConsultationOrWard($consultationIds, $wardIds, $status, $dateFrom, $dateTo, $q, $perPage, $page);
            return response()->json($drug, 200);
        }

        $currentHospitalId = $this->access->currentHospitalId();
        if ($currentHospitalId) {
            $consultationIds = Consultation::where('hospital_id', $currentHospitalId)->pluck('id');
            $wardIds = ward::where('hospital_id', $currentHospitalId)->pluck('id');
            $drug = $this->queryByConsultationOrWard($consultationIds, $wardIds, $status, $dateFrom, $dateTo, $q, $perPage, $page);
            return response()->json($drug, 200);
        }

        $drug = collect();

        return response()->json(DrugResource::collection($drug), 200);
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
    public function store(StoreDrugRequest $request)
    {
        $data = $request->validated();
        $drug = $this->drugs->create($data, $this->access);

        return response(new DrugResource($drug), 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Drug  $drug
     * @return \Illuminate\Http\Response
     */
    public function show(Drug $drug)
    {
        if (is_null($drug)) {
            return $this->sendError('Drug not found.');
            }
        
        $this->authorize('view', $drug);
        
            return response( new DrugResource($drug)
            , 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Drug  $drug
     * @return \Illuminate\Http\Response
     */
    public function edit(Drug $drug)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Drug  $drug
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateDrugRequest $request, $id)
    {
        $data = $request->validated();
        $drug=Drug::find($id);
        if (!$drug) {
            return $this->sendError('Drug not found.');
        }
        $this->authorize('update', $drug);

        $drug = $this->drugs->update($drug, $data, $this->access);

        return response(new DrugResource($drug), 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Drug  $drug
     * @return \Illuminate\Http\Response
     */
    public function destroy(Drug $drug)
    {
        $this->authorize('delete', $drug);
        $drug->delete();
   
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

        $query = Drug::where(function ($query) use ($consultationIds, $wardIds) {
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
            $query->whereDate('start_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('start_date', '<=', $dateTo);
        }
        if ($q) {
            $query->where('name', 'like', '%'.$q.'%');
        }

        $paginator = $query->orderBy('updated_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => DrugResource::collection($paginator->getCollection()),
            'pagination' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }
}
