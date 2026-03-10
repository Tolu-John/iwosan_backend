<?php

namespace App\Http\Controllers;

use App\Http\Requests\LabResults\StoreLabResultRequest;
use App\Http\Requests\LabResults\UpdateLabResultRequest;
use App\Http\Resources\LabResultResource;
use App\Http\Resources\LabTestResource;
use App\Models\LabResult;
use App\Models\LabResultAuditLog;
use App\Services\AccessService;
use App\Services\LabResultService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class LabResultController extends Controller
{
    private AccessService $access;
    private LabResultService $labResults;

    public function __construct(AccessService $access, LabResultService $labResults)
    {
        $this->access = $access;
        $this->labResults = $labResults;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $q = $request->query('q');
        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);

        $currentPatientId = $this->access->currentPatientId();
        if ($currentPatientId) {
            $query = LabResult::where('patient_id', $currentPatientId);
            return response($this->paginateResults($query, $dateFrom, $dateTo, $q, $perPage, $page), 200);
        }

        $currentCarerId = $this->access->currentCarerId();
        if ($currentCarerId) {
            $query = LabResult::where('carer_id', $currentCarerId);
            return response($this->paginateResults($query, $dateFrom, $dateTo, $q, $perPage, $page), 200);
        }

        $currentHospitalId = $this->access->currentHospitalId();
        if ($currentHospitalId) {
            $carerIds = \App\Models\Carer::where('hospital_id', $currentHospitalId)->pluck('id');
            $query = LabResult::whereIn('carer_id', $carerIds);
            return response($this->paginateResults($query, $dateFrom, $dateTo, $q, $perPage, $page), 200);
        }

        $labResult = collect();

        return response(LabResultResource::collection($labResult)
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
    public function store(StoreLabResultRequest $request)
    {
        $requestId = $request->attributes->get('request_id');
        Log::info('labresult.store.start', [
            'request_id' => $requestId,
            'has_file' => $request->hasFile('file'),
            'files_count' => is_array($request->file('files')) ? count($request->file('files')) : 0,
            'has_base64' => trim((string) $request->input('file_base64')) !== '',
            'auth_user_id' => optional(auth()->user())->id,
            'patient_id' => $request->input('patient_id'),
        ]);

        try {
            $data = $request->validated();
            $file = $request->file('file');
            $files = $request->file('files', []);
            $base64 = $request->input('file_base64');
            $base64Name = $request->input('file_name');

            Log::info('labresult.store.validated', [
                'request_id' => $requestId,
                'name' => $data['name'] ?? null,
                'lab_name' => $data['lab_name'] ?? null,
            ]);

            $labResult = $this->labResults->create($data, $file, $this->access, $files, $base64, $base64Name);

            Log::info('labresult.store.success', [
                'request_id' => $requestId,
                'lab_result_id' => $labResult->id,
            ]);

            return response(new LabResultResource($labResult), 200);
        } catch (Throwable $e) {
            Log::error('labresult.store.failed', [
                'request_id' => $requestId,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\LabResult  $labResult
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $labResult=LabResult::find($id);
      
        if (is_null($labResult)) {
            return $this->sendError('labResult not found.');
            }

        $this->authorize('view', $labResult);
        
            return response(new LabResultResource($labResult)
            , 200);
    }

    public function files($id)
    {
        $labResult = LabResult::find($id);
        if (is_null($labResult)) {
            return $this->sendError('labResult not found.');
        }

        $this->authorize('view', $labResult);

        return response([
            'id' => (string) $labResult->id,
            'result_pictures' => array_values(array_filter([
                $labResult->result_picture_front,
                $labResult->result_picture_back,
                $labResult->result_picture,
            ])),
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\LabResult  $labResult
     * @return \Illuminate\Http\Response
     */
    public function edit(LabResult $labResult)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\LabResult  $labResult
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateLabResultRequest $request, $id)
    {
        $data = $request->validated();
        $labResult=LabResult::find($id);
        if (!$labResult) {
            return $this->sendError('labResult not found.');
        }

        $this->authorize('update', $labResult);

        $file = $request->file('file');
        $files = $request->file('files', []);
        $base64 = $request->input('file_base64');
        $base64Name = $request->input('file_name');

        $labResult = $this->labResults->update($labResult, $data, $file, $this->access, $files, $base64, $base64Name);

        return response(new LabResultResource($labResult), 200);
        
    }

    

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\LabResult  $labResult
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $labResult=LabResult::find($id);
        if (!$labResult) {
            return $this->sendError('labResult not found.');
        }

        $this->authorize('delete', $labResult);
        $this->labResults->delete($labResult);
        $this->logAudit($labResult, 'deleted', $labResult->getAttributes());
   
   
       return response(['message' => 'Deleted']);
    }

    public function restore($id)
    {
        $labResult = LabResult::withTrashed()->find($id);
        if (!$labResult) {
            return $this->sendError('labResult not found.');
        }

        $this->authorize('update', $labResult);

        $labResult->restore();
        $this->logAudit($labResult, 'restored', $labResult->getAttributes());

        return response(new LabResultResource($labResult), 200);
    }

    public function audit(Request $request, $id)
    {
        $labResult = LabResult::withTrashed()->find($id);
        if (!$labResult) {
            return $this->sendError('labResult not found.');
        }

        $this->authorize('view', $labResult);

        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);

        $query = LabResultAuditLog::where('lab_result_id', $labResult->id)->orderBy('created_at', 'desc');
        $total = $query->count();
        $results = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response([
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'results' => $results,
        ], 200);
    }

    private function paginateResults($query, ?string $dateFrom, ?string $dateTo, ?string $q, int $perPage, int $page): array
    {
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', '%'.$q.'%')
                    ->orWhere('lab_name', 'like', '%'.$q.'%');
            });
        }

        $paginator = $query->orderBy('updated_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => LabResultResource::collection($paginator->getCollection()),
            'pagination' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    private function logAudit(LabResult $labResult, string $action, ?array $changes): void
    {
        LabResultAuditLog::create([
            'lab_result_id' => $labResult->id,
            'action' => $action,
            'changes' => $changes,
            'created_by' => auth()->id(),
            'created_role' => $this->currentActorRole(),
        ]);
    }

    private function currentActorRole(): string
    {
        if ($this->access->currentPatientId()) {
            return 'patient';
        }
        if ($this->access->currentCarerId()) {
            return 'carer';
        }
        if ($this->access->currentHospitalId()) {
            return 'hospital';
        }

        return 'system';
    }
    
    


    


    }
    
    
