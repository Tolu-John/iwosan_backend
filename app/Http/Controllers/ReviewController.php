<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReviewResource;
use App\Models\Carer;
use App\Models\Consultation;
use App\Models\Review;
use App\Services\AccessService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
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
        $status = $request->query('status');
        $ratingMin = $request->query('rating_min');
        $ratingMax = $request->query('rating_max');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $q = $request->query('q');
        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);

        $currentPatientId = $this->access->currentPatientId();
        if ($currentPatientId) {
            $query = Review::where('patient_id', $currentPatientId);
            return response($this->paginateReviews($query, $status, $ratingMin, $ratingMax, $dateFrom, $dateTo, $q, $perPage, $page, true), 200);
        }

        $currentCarerId = $this->access->currentCarerId();
        if ($currentCarerId) {
            $query = Review::where('carer_id', $currentCarerId)->where('status', 'published');
            return response($this->paginateReviews($query, $status, $ratingMin, $ratingMax, $dateFrom, $dateTo, $q, $perPage, $page, false), 200);
        }

        $currentHospitalId = $this->access->currentHospitalId();
        if ($currentHospitalId) {
            $consultationIds = Consultation::where('hospital_id', $currentHospitalId)->pluck('id');
            $query = $consultationIds->isEmpty()
                ? Review::whereRaw('1 = 0')
                : Review::whereIn('consultation_id', $consultationIds);
            return response($this->paginateReviews($query, $status, $ratingMin, $ratingMax, $dateFrom, $dateTo, $q, $perPage, $page, false), 200);
        }

        $Review = collect();
        return response(ReviewResource::collection($Review)
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

        $currentPatientId = $this->access->currentPatientId();
        if (!$currentPatientId) {
            return response()->json(['message' => 'Only patients can create reviews.'], 403);
        }

    $validator = Validator::make($request->all(), [
            'patient_id' => 'required',
            'carer_id' => 'required',
            'consultation_id' => 'required',
            'text' => 'required',
            'rating' => 'required|numeric|min:1|max:5',
            'recomm' => 'required',
            'tags' => 'nullable|array',
            'status' => 'nullable|string|in:pending,published,rejected',
           
        ]);

        if ($validator->fails()) {
            return response(['Validation errors' => $validator->errors()->all()], 422);
        }

        if ($currentPatientId && (int) $data['patient_id'] !== (int) $currentPatientId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $existing = Review::where('consultation_id', $data['consultation_id'])
            ->where('patient_id', $data['patient_id'])
            ->first();
        if ($existing) {
            return response()->json(['message' => 'Review already exists for this consultation.'], 422);
        }

        $consultation = Consultation::find($data['consultation_id']);
        if (!$consultation || $consultation->status !== 'completed') {
            return response()->json(['message' => 'Reviews are only allowed for completed consultations.'], 422);
        }

        if ((int) $consultation->patient_id !== (int) $data['patient_id']) {
            return response()->json(['message' => 'Consultation patient mismatch.'], 422);
        }

        if ((int) $consultation->carer_id !== (int) $data['carer_id']) {
            return response()->json(['message' => 'Consultation carer mismatch.'], 422);
        }

        $review = new Review();
        $review->patient_id=$data['patient_id'];
        $review->carer_id=$data['carer_id'];
        $review->consultation_id=$data['consultation_id'];
        $review->text=$data['text'];
        $review->rating=$data['rating'];
        $review->recomm=$data['recomm'];
        $review->tags = isset($data['tags']) ? json_encode($data['tags']) : null;
        $review->status = 'published';
        $review->save();

        $this->logAudit($review, 'created', $review->getAttributes());
        

        $carer=Carer::find($data['carer_id']);
        $carer->rating= Review::where('carer_id',$data['carer_id'])->avg('rating');
        $carer->save();



        return response( new ReviewResource($review)
        , 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Review  $Review
     * @return \Illuminate\Http\Response
     */
    public function show(Review $review)
    {
       
        if (is_null($review)) {
            return $this->sendError('Review not found.');
            }

        $this->authorize('view', $review);
        
            return response(new ReviewResource($review)
            , 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Review  $Review
     * @return \Illuminate\Http\Response
     */
    public function edit(Review $Review)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Review  $Review
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        
         
        $data=$request->all();

    $validator = Validator::make($request->all(), [
            'text' => 'required',
            'rating' => 'required|numeric|min:1|max:5',
            'recomm' => 'required',
            'tags' => 'nullable|array',
            'status' => 'nullable|string|in:pending,published,rejected',
           
        ]);
        
        if ($validator->fails()) {
            return response(['Validation errors' => $validator->errors()->all()], 422);
        }


        
        $review=Review::find($id);
        if (!$review) {
            return $this->sendError('Review not found.');
        }

        $currentPatientId = $this->access->currentPatientId();
        if (!$currentPatientId || (int) $review->patient_id !== (int) $currentPatientId) {
            return response()->json(['message' => 'Only the patient can update this review.'], 403);
        }

        if (isset($data['patient_id']) && (int) $data['patient_id'] !== (int) $review->patient_id) {
            return response()->json(['message' => 'Patient mismatch.'], 422);
        }
        if (isset($data['carer_id']) && (int) $data['carer_id'] !== (int) $review->carer_id) {
            return response()->json(['message' => 'Carer mismatch.'], 422);
        }
        if (isset($data['consultation_id']) && (int) $data['consultation_id'] !== (int) $review->consultation_id) {
            return response()->json(['message' => 'Consultation mismatch.'], 422);
        }
        $before = $review->getAttributes();
        $review->text=$data['text'];
        $review->rating=$data['rating'];
        $review->recomm=$data['recomm'];
        $review->tags = isset($data['tags']) ? json_encode($data['tags']) : null;
        $review->status = $review->status ?? 'published';
        $review->edited_at = Carbon::now();
        $review->save();
        $this->logAudit($review, 'updated', $this->diffChanges($before, $review->getAttributes()));
        
        return response( new ReviewResource($review)
        , 200);

    }

    public function respond(Request $request, $id)
    {
        $review = Review::find($id);
        if (!$review) {
            return $this->sendError('Review not found.');
        }

        $this->authorize('view', $review);

        if (!$this->access->currentCarerId() && !$this->access->currentHospitalId()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'response_text' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response(['Validation errors' => $validator->errors()->all()], 422);
        }

        $review->response_text = $request->response_text;
        $review->response_at = Carbon::now();
        $review->response_by = Auth::id();
        $review->save();
        $this->logAudit($review, 'responded', [
            'response_text' => $review->response_text,
            'response_at' => $review->response_at,
            'response_by' => $review->response_by,
        ]);

        return response(new ReviewResource($review), 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Review  $Review
     * @return \Illuminate\Http\Response
     */
    public function destroy(Review $Review)
    {
        if ($this->access->currentHospitalId()) {
            $Review->status = 'rejected';
            $Review->deleted_reason = request()->input('reason');
            $Review->save();
            $this->logAudit($Review, 'moderated', [
                'status' => 'rejected',
                'reason' => $Review->deleted_reason,
            ]);
            return response(['message' => 'Review rejected'], 200);
        }

        $currentPatientId = $this->access->currentPatientId();
        if (!$currentPatientId || (int) $Review->patient_id !== (int) $currentPatientId) {
            return response()->json(['message' => 'Only the patient can delete this review.'], 403);
        }

        $Review->delete();
        $this->logAudit($Review, 'deleted', $Review->getAttributes());

        return response(['message' => 'Deleted']);
    }

    public function audit(Request $request, $id)
    {
        $review = Review::find($id);
        if (!$review) {
            return $this->sendError('Review not found.');
        }

        $this->authorize('view', $review);

        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);

        $query = \App\Models\ReviewAuditLog::where('review_id', $review->id)->orderBy('created_at', 'desc');
        $total = $query->count();
        $results = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response([
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'results' => $results,
        ], 200);
    }

    private function paginateReviews($query, ?string $status, ?string $ratingMin, ?string $ratingMax, ?string $dateFrom, ?string $dateTo, ?string $q, int $perPage, int $page, bool $hideRejected): array
    {
        if ($hideRejected) {
            $query->where('status', '!=', 'rejected');
        }

        if ($status) {
            $query->where('status', $status);
        }
        if ($ratingMin !== null) {
            $query->where('rating', '>=', (float) $ratingMin);
        }
        if ($ratingMax !== null) {
            $query->where('rating', '<=', (float) $ratingMax);
        }
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
        if ($q) {
            $query->where('text', 'like', '%'.$q.'%');
        }

        $paginator = $query->orderBy('updated_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => ReviewResource::collection($paginator->getCollection()),
            'pagination' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    private function logAudit(Review $review, string $action, ?array $changes): void
    {
        \App\Models\ReviewAuditLog::create([
            'review_id' => $review->id,
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
        if ($this->access->currentCarerId()) {
            return 'carer';
        }
        if ($this->access->currentHospitalId()) {
            return 'hospital';
        }

        return 'system';
    }

    private function diffChanges(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $key => $value) {
            if (!array_key_exists($key, $before)) {
                continue;
            }
            if ($before[$key] !== $value) {
                $changes[$key] = [
                    'from' => $before[$key],
                    'to' => $value,
                ];
            }
        }
        return $changes;
    }
}
