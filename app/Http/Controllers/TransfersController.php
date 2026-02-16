<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransferRequest;
use App\Http\Requests\UpdateTransferStatusRequest;
use App\Http\Resources\TransferResource;
use App\Models\Transfers;
use App\Services\AccessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransfersController extends Controller
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
        $currentCarerId = $this->access->currentCarerId();
        if ($currentCarerId) {
            $query = Transfers::where('carer_id', $currentCarerId)->orderBy('updated_at', 'desc');
            return response($this->withSummary($query, request()), 200);
        }

        $currentHospitalId = $this->access->currentHospitalId();
        if ($currentHospitalId) {
            $query = Transfers::where('hospital_id', $currentHospitalId)->orderBy('updated_at', 'desc');
            return response($this->withSummary($query, request()), 200);
        }

        $transfer = collect();
        return response(TransferResource::collection($transfer)
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
    public function store(StoreTransferRequest $request)
    {
        $data = $request->validated();

        $currentCarerId = $this->access->currentCarerId();
        if ($currentCarerId && (int) $data['carer_id'] !== (int) $currentCarerId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $currentHospitalId = $this->access->currentHospitalId();
        if ($currentHospitalId && (int) $data['hospital_id'] !== (int) $currentHospitalId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
    
           // $transfer=Transfer::create($request->all());
           
           
            $transfer= new Transfers();
            $transfer->payment_id=$data['payment_id'];
            $transfer->type_id=$data['type_id'];
            $transfer->type=$data['type'] ?? null;
            $transfer->hospital_id=$data['hospital_id'];
            $transfer->carer_id=$data['carer_id'];
            $transfer->recipient=$data['recipient'];
            $transfer->amount=$data['amount'];
            $transfer->currency=$data['currency'] ?? 'NGN';
            $transfer->method=$data['method'] ?? null;
            $transfer->reference=$data['reference'] ?? null;
            $transfer->reason=$data['reason'];
            $transfer->status='pending';
            $transfer->requested_at=Carbon::now();
            $transfer->requested_by=auth()->id();
            $transfer->requested_role=$this->currentActorRole();
        
            $transfer->save();



            return response( new TransferResource($transfer)
            , 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Transfers  $transfers
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $transfer=Transfers::find( $id);
        if (is_null($transfer)) {
            return $this->sendError('transfer not found.');
            }

        $this->authorize('view', $transfer);
        
            return response( new TransferResource($transfer)
            , 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Transfers  $transfers
     * @return \Illuminate\Http\Response
     */
    public function edit(Transfers $transfers)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Transfers  $transfers
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateTransferStatusRequest $request, $id)
    {
        $data = $request->validated();

        $transfer=Transfers::find($id);
        if (!$transfer) {
            return $this->sendError('transfer not found.');
        }

        $this->authorize('update', $transfer);

        if (!$this->access->currentHospitalId()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $this->assertTransitionAllowed($transfer->status, $data['status']);

        $transfer->status = $data['status'];
        $transfer->reference = $data['reference'] ?: $transfer->reference;
        $transfer->failure_reason = $data['failure_reason'] ?: null;

        if ($data['status'] === 'processing' && !$transfer->processed_at) {
            $transfer->processed_at = Carbon::now();
        }
        if ($data['status'] === 'paid' && !$transfer->paid_at) {
            $transfer->paid_at = Carbon::now();
        }
        if ($data['status'] === 'failed' && !$transfer->failed_at) {
            $transfer->failed_at = Carbon::now();
        }

        $transfer->save();

        return response(new TransferResource($transfer), 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Transfers  $transfers
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
     
        $transfer=Transfers::find($id);
        if (!$transfer) {
            return $this->sendError('transfer not found.');
        }

        $this->authorize('delete', $transfer);
        $transfer->delete();
   
        return response(['message' => 'Deleted']);
    }

    private function assertTransitionAllowed(?string $from, string $to): void
    {
        $from = $from ?: 'pending';
        if ($from === $to) {
            return;
        }

        $allowed = [
            'pending' => ['processing', 'failed'],
            'processing' => ['paid', 'failed'],
            'paid' => [],
            'failed' => ['processing'],
            'reversed' => [],
        ];

        $next = $allowed[$from] ?? [];
        if (!in_array($to, $next, true)) {
            abort(422, 'Invalid transfer status transition.');
        }
    }

    private function withSummary($query, Request $request): array
    {
        $status = $request->query('status');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $recipient = $request->query('recipient');
        $reference = $request->query('reference');
        $reason = $request->query('reason');
        $minAmount = $request->query('min_amount');
        $maxAmount = $request->query('max_amount');
        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);

        if ($status) {
            $query->where('status', $status);
        }
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
        if ($recipient) {
            $query->where('recipient', 'like', '%'.$recipient.'%');
        }
        if ($reference) {
            $query->where('reference', 'like', '%'.$reference.'%');
        }
        if ($reason) {
            $query->where('reason', 'like', '%'.$reason.'%');
        }
        if ($minAmount !== null) {
            $query->where('amount', '>=', (float) $minAmount);
        }
        if ($maxAmount !== null) {
            $query->where('amount', '<=', (float) $maxAmount);
        }

        $summaryAll = (clone $query)->get();
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $sum = function ($status) use ($summaryAll) {
            return $summaryAll->where('status', $status)->sum(fn ($t) => (float) $t->amount);
        };

        return [
            'summary' => [
                'total' => $summaryAll->count(),
                'pending' => $summaryAll->where('status', 'pending')->count(),
                'processing' => $summaryAll->where('status', 'processing')->count(),
                'paid' => $summaryAll->where('status', 'paid')->count(),
                'failed' => $summaryAll->where('status', 'failed')->count(),
                'total_amount' => $summaryAll->sum(fn ($t) => (float) $t->amount),
                'pending_amount' => $sum('pending'),
                'processing_amount' => $sum('processing'),
                'paid_amount' => $sum('paid'),
            ],
            'transfers' => TransferResource::collection($paginator->getCollection()),
            'pagination' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    private function currentActorRole(): string
    {
        if ($this->access->currentCarerId()) {
            return 'carer';
        }
        if ($this->access->currentHospitalId()) {
            return 'hospital';
        }
        if ($this->access->currentPatientId()) {
            return 'patient';
        }

        return 'system';
    }
}
