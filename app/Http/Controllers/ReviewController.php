<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReviewResource;
use App\Models\Carer;
use App\Models\Consultation;
use App\Models\Review;
use App\Services\AccessService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $currentPatientId = $this->access->currentPatientId();
        if (!$currentPatientId) {
            return response()->json(['message' => 'Only patients can create reviews.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|integer|exists:patients,id',
            'carer_id' => 'required|integer|exists:carers,id',
            'consultation_id' => 'required|integer|exists:consultations,id',
            'text' => 'required|string|min:2|max:2000',
            'rating' => 'required|numeric|min:1|max:5',
            'recomm' => 'required|in:yes,no,1,0,true,false',
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }
        $data = $validator->validated();

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

        try {
            $review = DB::transaction(function () use ($data) {
                $review = new Review();
                $review->patient_id = (int) $data['patient_id'];
                $review->carer_id = (int) $data['carer_id'];
                $review->consultation_id = (int) $data['consultation_id'];
                $review->text = trim($data['text']);
                $review->rating = (float) $data['rating'];
                $review->recomm = $this->normalizeRecommend($data['recomm']);
                $review->tags = $data['tags'] ?? null;
                $review->status = 'published';
                $review->save();

                $this->logAudit($review, 'created', $review->getAttributes());

                $carer = Carer::find($review->carer_id);
                if ($carer) {
                    $carer->rating = (float) Review::where('carer_id', $review->carer_id)->avg('rating');
                    $carer->save();
                }

                return $review->load(['patient.user', 'consultation.carer.user', 'consultation.carer.hospital']);
            });
        } catch (QueryException $e) {
            // Handle race condition against unique(patient_id, consultation_id).
            if ((string) $e->getCode() === '23000') {
                return response()->json(['message' => 'Review already exists for this consultation.'], 422);
            }
            throw $e;
        }

        return response(new ReviewResource($review), 200);
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
        
            return response(new ReviewResource($review->loadMissing(['patient.user', 'consultation.carer.user', 'consultation.carer.hospital']))
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
        if ($this->access->currentPatientId()) {
            return response()->json(['message' => 'Patients cannot edit reviews once submitted.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'text' => 'required|string|min:2|max:2000',
            'rating' => 'required|numeric|min:1|max:5',
            'recomm' => 'required|in:yes,no,1,0,true,false',
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'string|max:64',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }
        $data = $validator->validated();


        
        $review=Review::find($id);
        if (!$review) {
            return $this->sendError('Review not found.');
        }

        return response()->json(['message' => 'Review updates are not allowed.'], 403);

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
        if ($currentPatientId) {
            return response()->json(['message' => 'Patients cannot delete reviews once submitted.'], 403);
        }

        return response()->json(['message' => 'Review deletion is not allowed.'], 403);
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

        $paginator = $query
            ->with(['patient.user', 'consultation.carer.user', 'consultation.carer.hospital'])
            ->orderBy('updated_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

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

    private function normalizeRecommend(string $value): bool
    {
        $normalized = strtolower(trim($value));
        return in_array($normalized, ['yes', '1', 'true'], true);
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
