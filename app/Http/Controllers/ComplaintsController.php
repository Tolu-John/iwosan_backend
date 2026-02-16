<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreComplaintRequest;
use App\Http\Requests\UpdateComplaintRequest;
use App\Http\Requests\UpdateComplaintStatusRequest;
use App\Http\Resources\ComplaintResource;
use App\Models\ComplaintAuditLog;
use App\Models\Complaints;
use App\Services\AccessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class ComplaintsController extends Controller
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
    public function index(Request $request)
    {
        $currentPatientId = $this->access->currentPatientId();
        if ($currentPatientId) {
            $query = Complaints::where('patient_id', $currentPatientId);
            return response($this->withSummary($query, $request), 200);
        }

        $currentHospitalId = $this->access->currentHospitalId();
        if ($currentHospitalId) {
            $query = Complaints::where('hospital_id', $currentHospitalId);
            return response($this->withSummary($query, $request), 200);
        }

        $complaint = collect();

        return response( ComplaintResource::collection($complaint)
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
    public function store(StoreComplaintRequest $request)
    {
        $data = $request->validated();

        $currentPatientId = $this->access->currentPatientId();
        if (!$currentPatientId) {
            return response()->json(['message' => 'Only patients can create complaints.'], 403);
        }
        if ((int) $data['patient_id'] !== (int) $currentPatientId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $complaints=new Complaints();
        $complaints->patient_id=$data['patient_id'];
        $complaints->hospital_id=$data['hospital_id'];
        $complaints->title=$data['title'];
        $complaints->complaint=$data['complaint'];
        $complaints->category=$data['category'];
        $complaints->severity=$data['severity'];
        $complaints->status='open';
        $complaints->save();

        $this->logAudit($complaints, 'created', $complaints->getAttributes());
    
        return response(new ComplaintResource($complaints)
        , 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\complaints  $complaints
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {

        $complaints=Complaints::find( $id);
        
        if (is_null($complaints)) {
        return $this->sendError('$complaints not found.');
        }

        $this->authorize('view', $complaints);
    
        return response(['message' => 'Retrieved successfully',
        'complaints' => new ComplaintResource($complaints)
        ]
        , 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\complaints  $complaints
     * @return \Illuminate\Http\Response
     */
    public function edit(complaints $complaints)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\complaints  $complaints
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateComplaintRequest $request, $id)
    {
        $data = $request->validated();

        $complaints = Complaints::find( $id);
        if (!$complaints) {
            return $this->sendError('Complaint not found.');
        }

        $currentPatientId = $this->access->currentPatientId();
        if (!$currentPatientId || (int) $complaints->patient_id !== (int) $currentPatientId) {
            return response()->json(['message' => 'Only the patient can update this complaint.'], 403);
        }
        if (isset($data['patient_id']) && (int) $data['patient_id'] !== (int) $complaints->patient_id) {
            return response()->json(['message' => 'Patient mismatch.'], 422);
        }
        if (isset($data['hospital_id']) && (int) $data['hospital_id'] !== (int) $complaints->hospital_id) {
            return response()->json(['message' => 'Hospital mismatch.'], 422);
        }
        $complaints->title=$data['title'];
        $complaints->complaint=$data['complaint'];
        $complaints->category=$data['category'];
        $complaints->severity=$data['severity'];
        $complaints->save();

        $this->logAudit($complaints, 'updated', $complaints->getAttributes());
        
        
        return response( new ComplaintResource($complaints)
        , 200);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\complaints  $complaints
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $complaints=Complaints::find( $id);
        if (!$complaints) {
            return $this->sendError('Complaint not found.');
        }

        $currentPatientId = $this->access->currentPatientId();
        if (!$currentPatientId || (int) $complaints->patient_id !== (int) $currentPatientId) {
            return response()->json(['message' => 'Only the patient can delete this complaint.'], 403);
        }
        $complaints->delete();
        $this->logAudit($complaints, 'deleted', $complaints->getAttributes());
   
        return response(['message' => 'Deleted']);
     
    }

    public function updateStatus(UpdateComplaintStatusRequest $request, $id)
    {
        $complaints = Complaints::find($id);
        if (!$complaints) {
            return $this->sendError('Complaint not found.');
        }

        $this->authorize('update', $complaints);

        if (!$this->access->currentHospitalId()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $from = $complaints->status;
        $to = $request->status;
        $this->assertTransitionAllowed($from, $to);

        $complaints->status = $to;
        if ($request->has('response_notes')) {
            $complaints->response_notes = $request->response_notes;
        }
        if ($request->has('resolution_notes')) {
            $complaints->resolution_notes = $request->resolution_notes;
        }
        if ($to === 'rejected' && $request->has('rejection_reason')) {
            $complaints->rejection_reason = $request->rejection_reason;
        }

        if (!$complaints->first_response_at && in_array($to, ['in_review', 'resolved', 'closed'], true)) {
            $complaints->first_response_at = Carbon::now();
        }
        if ($to === 'resolved' && !$complaints->resolved_at) {
            $complaints->resolved_at = Carbon::now();
        }
        if ($to === 'closed' && !$complaints->closed_at) {
            $complaints->closed_at = Carbon::now();
        }
        if ($to === 'rejected' && !$complaints->rejected_at) {
            $complaints->rejected_at = Carbon::now();
        }

        $complaints->save();
        $this->logAudit($complaints, 'status_update', [
            'from' => $from,
            'to' => $to,
            'response_notes' => $complaints->response_notes,
            'resolution_notes' => $complaints->resolution_notes,
            'rejection_reason' => $complaints->rejection_reason,
        ]);

        return response(new ComplaintResource($complaints), 200);
    }

    public function audit(Request $request, $id)
    {
        $complaints = Complaints::find($id);
        if (!$complaints) {
            return $this->sendError('Complaint not found.');
        }

        $this->authorize('view', $complaints);

        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);

        $query = ComplaintAuditLog::where('complaint_id', $complaints->id)->orderBy('created_at', 'desc');
        $total = $query->count();
        $results = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response([
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'results' => $results,
        ], 200);
    }

    private function withSummary($query, Request $request): array
    {
        $status = $request->query('status');
        $severity = $request->query('severity');
        $category = $request->query('category');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $q = $request->query('q');
        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);

        if ($status) {
            $query->where('status', $status);
        }
        if ($severity) {
            $query->where('severity', $severity);
        }
        if ($category) {
            $query->where('category', $category);
        }
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', '%'.$q.'%')
                    ->orWhere('complaint', 'like', '%'.$q.'%');
            });
        }

        $all = (clone $query)->get();
        $summary = [
            'total' => $all->count(),
            'open' => $all->where('status', 'open')->count(),
            'in_review' => $all->where('status', 'in_review')->count(),
            'resolved' => $all->where('status', 'resolved')->count(),
            'closed' => $all->where('status', 'closed')->count(),
            'rejected' => $all->where('status', 'rejected')->count(),
        ];

        $paginated = $query->with(['patient', 'hospital', 'assignee'])
            ->orderBy('updated_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'summary' => $summary,
            'data' => ComplaintResource::collection($paginated->getCollection()),
            'pagination' => [
                'total' => $paginated->total(),
                'page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ];
    }

    private function assertTransitionAllowed(?string $from, string $to): void
    {
        $from = $from ?: 'open';
        if ($from === $to) {
            return;
        }

        $allowed = [
            'open' => ['in_review', 'rejected'],
            'in_review' => ['resolved', 'rejected'],
            'resolved' => ['closed'],
            'closed' => [],
            'rejected' => [],
        ];

        $next = $allowed[$from] ?? [];
        if (!in_array($to, $next, true)) {
            abort(422, 'Invalid complaint status transition.');
        }
    }

    private function logAudit(Complaints $complaints, string $action, ?array $changes): void
    {
        ComplaintAuditLog::create([
            'complaint_id' => $complaints->id,
            'action' => $action,
            'changes' => $changes,
            'created_by' => Auth::id(),
            'created_role' => $this->currentActorRole(),
        ]);
    }

    private function currentActorRole(): string
    {
        if ($this->access->currentPatientId()) {
            return 'patient';
        }
        if ($this->access->currentHospitalId()) {
            return 'hospital';
        }

        return 'system';
    }
}
