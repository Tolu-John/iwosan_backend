<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payments\StorePaymentRequest;
use App\Http\Requests\Payments\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\AccessService;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    private AccessService $access;
    private PaymentService $payments;

    public function __construct(AccessService $access, PaymentService $payments)
    {
        $this->access = $access;
        $this->payments = $payments;
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
            $query = Payment::where('patient_id', $currentPatientId)->orderBy('updated_at', 'desc');
            return $this->respondWithPayments($query, $request);
        }

        $currentCarerId = $this->access->currentCarerId();
        if ($currentCarerId) {
            $query = Payment::where('carer_id', $currentCarerId)->orderBy('updated_at', 'desc');
            return $this->respondWithPayments($query, $request);
        }

        $currentHospitalId = $this->access->currentHospitalId();
        if ($currentHospitalId) {
            $carerIds = \App\Models\Carer::where('hospital_id', $currentHospitalId)->pluck('id');
            $query = Payment::whereIn('carer_id', $carerIds)->orderBy('updated_at', 'desc');
            return $this->respondWithPayments($query, $request);
        }

        $payment = collect();

        return response(PaymentResource::collection($payment)
        , 200);
    }

    private function respondWithPayments($query, Request $request)
    {
        $status = $request->query('status');
        $type = $request->query('type');
        $method = $request->query('method');
        $reference = $request->query('reference');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $minAmount = $request->query('min_amount');
        $maxAmount = $request->query('max_amount');

        if ($status) {
            $query->where('status', $status);
        }
        if ($type) {
            $query->where('type', $type);
        }
        if ($method) {
            $query->where('method', $method);
        }
        if ($reference) {
            $query->where('reference', 'like', '%'.$reference.'%');
        }
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
        if ($minAmount !== null) {
            $query->where('price', '>=', (float) $minAmount);
        }
        if ($maxAmount !== null) {
            $query->where('price', '<=', (float) $maxAmount);
        }

        $shouldPaginate = $request->has('page') || $request->has('per_page') || $request->boolean('paginate');
        if ($shouldPaginate) {
            $perPage = (int) $request->query('per_page', 20);
            $page = (int) $request->query('page', 1);
            $paginator = $query->paginate($perPage, ['*'], 'page', $page);
            return response(PaymentResource::collection($paginator), 200);
        }

        $payments = $query->get();
        return response(PaymentResource::collection($payments), 200);
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
    public function store(StorePaymentRequest $request)
    {
        $data = $request->validated();
        $payment = $this->payments->create($data, $this->access, false);

        return response(new PaymentResource($payment), 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Payment  $payment
     * @return \Illuminate\Http\Response
     */
    public function show(Payment $payment)
    {
        if (is_null($payment)) {
            return $this->sendError('Payment not found.');
            }

        $this->authorize('view', $payment);
        
            return response( new PaymentResource($payment)
            , 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Payment  $payment
     * @return \Illuminate\Http\Response
     */
    public function edit(Payment $payment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Payment  $payment
     * @return \Illuminate\Http\Response
     */
    public function update(UpdatePaymentRequest $request, $id)
    {

        $data = $request->validated();
        $payment=Payment::find($id);
        if (!$payment) {
            return $this->sendError('Payment not found.');
        }

        $this->authorize('update', $payment);

        $payment = $this->payments->update($payment, $data, $this->access, false);
        
        return response(new PaymentResource($payment), 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Payment  $payment
     * @return \Illuminate\Http\Response
     */
    public function destroy(Payment $payment)
    {
        $this->authorize('delete', $payment);
        $payment->delete();
   
        return response(['message' => 'Deleted']);
    }
}
