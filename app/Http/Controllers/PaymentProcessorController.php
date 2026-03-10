<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\PaystackService;
use App\Models\Payment;
use App\Services\AccessService;
use App\Services\PaymentService;
use App\Services\RefundPolicyService;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PaymentProcessorController extends Controller
{
    private PaystackService $paystack;
    private AccessService $access;
    private RefundPolicyService $refunds;
    private PaymentService $payments;

    public function __construct(
        PaystackService $paystack,
        AccessService $access,
        RefundPolicyService $refunds,
        PaymentService $payments
    )
    {
        $this->paystack = $paystack;
        $this->access = $access;
        $this->refunds = $refunds;
        $this->payments = $payments;
    }


public function create_transfer_recipient(Request $request){
    
    $data=$request->all();
        
    $validator = Validator::make($request->all(), [
            'account_name' => 'required',
            'account_number' => 'required',
            'bank_code' => 'required',
        ]);

    if ($validator->fails()) {
        return response(['Validation errors' => $validator->errors()->all()], 422);
    }


    $fields = [
      "type" => "nuban",
      "name" =>$data['account_name'],
      "account_number" => $data['account_number'],
      "bank_code" =>  $data['bank_code'],
      "currency" => "NGN"
    ];
    
    $result = $this->paystack->request('post', 'transferrecipient', $fields);

    if ($result instanceof HttpResponse) {
        return $result;
    }

    $data = $result['data'] ?? [];

    return response([
        'status' => $result['status'] ?? false,
        'message' => $result['message'] ?? 'Paystack request failed',
        'data1' => $data["recipient_code"] ?? null,
    ], 200);

}

public function resolve_account_details(Request $request){
 
    $data=$request->all();
        

    $validator = Validator::make($request->all(), [
            'account_number' => 'required',
            'bank_code' => 'required',
        ]);

    if ($validator->fails()) {
        return response(['Validation errors' => $validator->errors()->all()], 422);
    }

    $endpoint = "bank/resolve?account_number=".$data['account_number']."&bank_code=".$data['bank_code'];
    $result = $this->paystack->request('get', $endpoint);

    if ($result instanceof HttpResponse) {
        return $result;
    }

    $data = $result['data'] ?? [];
    return response([
        'status' => $result['status'] ?? false,
        'message' => $result['message'] ?? 'Paystack request failed',
        'data1' => $data["account_number"] ?? null,
        'data2' => $data["account_name"] ?? null,
    ], 200);


}


public function update_transfer_recipient(Request $request){

 
    $data=$request->all();
        
    $validator = Validator::make($request->all(), [
            'recipient_code' => 'required',
            'account_name' => 'required',
        ]);

    if ($validator->fails()) {
        return response(['Validation errors' => $validator->errors()->all()], 422);
    }


    $fields = [
      'name' => $data['account_name']
    ];
    $endpoint = "transferrecipient/".$data['recipient_code'];
    $result = $this->paystack->request('put', $endpoint, $fields);

    if ($result instanceof HttpResponse) {
        return $result;
    }

    $resData = $result['data'] ?? [];
    return response([
        'status' => $result['status'] ?? false,
        'message' => $result['message'] ?? 'Paystack request failed',
        'data1' => $resData["recipient_code"] ?? null,
    ], 200);
  

}


public function delete_transfer_recipient(Request $request){
    $data=$request->all();
        
    $validator = Validator::make($request->all(), [
            'recipient_code' => 'required',
        ]);

    if ($validator->fails()) {
        return response(['Validation errors' => $validator->errors()->all()], 422);
    }

    $endpoint = "transferrecipient/".$data['recipient_code'];
    $result = $this->paystack->request('delete', $endpoint);

    if ($result instanceof HttpResponse) {
        return $result;
    }

    return response([
        'status' => $result['status'] ?? false,
        'message' => $result['message'] ?? 'Paystack request failed',
    ], 200);

}

public function initialize_payment(Request $request)
{
    $validator = Validator::make($request->all(), [
        'amount' => 'required',
        'email' => 'required|email',
        'payment_id' => 'nullable|integer',
        'reference' => 'nullable|string|max:255',
        // Mobile deep-link callbacks (e.g. iwosan://payments/callback) are valid here.
        'callback_url' => 'nullable|string|max:2048',
        'currency' => 'nullable|string|max:10',
        'metadata' => 'nullable|array',
        'channels' => 'nullable|array',
    ]);

    if ($validator->fails()) {
        return response(['Validation errors' => $validator->errors()->all()], 422);
    }

    $fields = [
        'amount' => $request->input('amount'),
        'email' => $request->input('email'),
        'reference' => $request->input('reference'),
        'callback_url' => $request->input('callback_url'),
        'currency' => $request->input('currency'),
        'metadata' => $request->input('metadata'),
        'channels' => $request->input('channels'),
    ];

    $result = $this->paystack->request('post', 'transaction/initialize', array_filter($fields, fn($v) => $v !== null));

    if ($result instanceof HttpResponse) {
        return $result;
    }

    $data = $result['data'] ?? [];
    $reference = $data['reference'] ?? null;

    $paymentId = $request->input('payment_id');
    if ($paymentId) {
        $payment = Payment::find($paymentId);
        if (!$payment) {
            return response(['message' => 'Payment not found.'], 404);
        }

        $deny = $this->access->denyIfFalse($this->access->canAccessPayment($payment));
        if ($deny) {
            return $deny;
        }

        $fromStatus = $payment->status;
        if ($reference) {
            $payment->reference = $reference;
        }
        $payment->gateway = 'paystack';
        if ($payment->status === 'pending') {
            $payment->status = 'processing';
            $payment->processing_at = $payment->processing_at ?? Carbon::now();
        }
        $payment->save();
        $this->payments->logStatusChange($payment, $fromStatus, $payment->status, 'api', 'paystack:init');
    }

    return response([
        'status' => $result['status'] ?? false,
        'message' => $result['message'] ?? 'Paystack request failed',
        'data' => [
            'authorization_url' => $data['authorization_url'] ?? null,
            'access_code' => $data['access_code'] ?? null,
            'reference' => $reference,
        ],
    ], 200);
}

public function verify_payment(Request $request)
{
    $validator = Validator::make($request->all(), [
        'reference' => 'required|string|max:255',
        'payment_id' => 'nullable|integer',
    ]);

    if ($validator->fails()) {
        return response(['Validation errors' => $validator->errors()->all()], 422);
    }

    $reference = $request->input('reference');
    $endpoint = 'transaction/verify/' . $reference;
    $result = $this->paystack->request('get', $endpoint);

    if ($result instanceof HttpResponse) {
        return $result;
    }

    $data = $result['data'] ?? [];
    $gatewayStatus = $data['status'] ?? null;
    $paymentStatus = match ($gatewayStatus) {
        'success' => 'paid',
        'failed', 'abandoned' => 'failed',
        'processing' => 'processing',
        default => null,
    };

    $payment = null;
    $paymentId = $request->input('payment_id');
    if ($paymentId) {
        $payment = Payment::find($paymentId);
    }
    if (!$payment) {
        $payment = Payment::where('reference', $reference)
            ->orWhere('code', $reference)
            ->first();
    }

    if ($payment) {
        $deny = $this->access->denyIfFalse($this->access->canAccessPayment($payment));
        if ($deny) {
            return $deny;
        }

        $fromStatus = $payment->status;
        if ($paymentStatus) {
            $payment->status = $paymentStatus;
        }
        $payment->gateway = 'paystack';
        $payment->reference = $reference;
        $payment->gateway_transaction_id = $data['id'] ?? $payment->gateway_transaction_id;
        $payment->channel = $data['channel'] ?? $payment->channel;
        $payment->currency = $data['currency'] ?? $payment->currency;
        $payment->fees = isset($data['fees']) ? (int) $data['fees'] : $payment->fees;
        $payment->gateway_payload = $data ?: $payment->gateway_payload;

        if ($paymentStatus === 'paid') {
            $payment->verified_at = $payment->verified_at ?? Carbon::now();
            $payment->paid_at = $payment->paid_at ?? Carbon::now();
        }
        if ($paymentStatus === 'processing') {
            $payment->processing_at = $payment->processing_at ?? Carbon::now();
        }
        if ($paymentStatus === 'failed') {
            $payment->failed_at = $payment->failed_at ?? Carbon::now();
        }

        $payment->save();
        if ($paymentStatus) {
            $this->payments->logStatusChange($payment, $fromStatus, $payment->status, 'api', 'paystack:verify');
        }
    }

    return response([
        'status' => $result['status'] ?? false,
        'message' => $result['message'] ?? 'Paystack request failed',
        'data' => [
            'reference' => $reference,
            'gateway_status' => $gatewayStatus,
            'payment_status' => $paymentStatus,
            'gateway_id' => $data['id'] ?? null,
        ],
    ], 200);
}

public function initiate_transfer(Request $request){
    $data=$request->all();
        
    $validator = Validator::make($request->all(), [
            'reason' => 'required',
            'amount' => 'required',
            'recipient' => 'required',
        ]);

    if ($validator->fails()) {
        return response(['Validation errors' => $validator->errors()->all()], 422);
    }


    $fields = [
      "source" => "balance", 
      "reason" => $data['reason'], 
      "amount" => $data['amount'],
      "recipient" => $data['recipient']
      ];
    $result = $this->paystack->request('post', 'transfer', $fields);

    if ($result instanceof HttpResponse) {
        return $result;
    }

    $resData = $result['data'] ?? [];
    return response([
        'status' => $result['status'] ?? false,
        'message' => $result['message'] ?? 'Paystack request failed',
        'data1' => $resData["transfer_code"] ?? null,
        'data2' => $resData["status"] ?? null,
    ], 200);
  
}


public function finalize_transfer(Request $request){

    $data=$request->all();
        
    $validator = Validator::make($request->all(), [
            'transfer_code' => 'required',
            'otp' => 'required',
            
        ]);

    if ($validator->fails()) {
        return response(['Validation errors' => $validator->errors()->all()], 422);
    }


    $fields = [
      "transfer_code" => $data['transfer_code'], 
      "otp" =>  $data['otp']
    ];
    $result = $this->paystack->request('post', 'transfer/finalize_transfer', $fields);

    if ($result instanceof HttpResponse) {
        return $result;
    }

    $resData = $result['data'] ?? [];
    return response([
        'status' => $result['status'] ?? false,
        'message' => $result['message'] ?? 'Paystack request failed',
        'data1' => $resData["transfer_code"] ?? null,
        'data2' => $resData["status"] ?? null,
    ], 200);
}

public function verify_transfer(Request $request){

    $data=$request->all();
        
    $validator = Validator::make($request->all(), [

            'reference' => 'required',

        ]);

    if ($validator->fails()) {
        return response(['Validation errors' => $validator->errors()->all()], 422);
    }

    $endpoint = "transfer/verify/".$data['reference'];
    $result = $this->paystack->request('get', $endpoint);

    if ($result instanceof HttpResponse) {
        return $result;
    }

    $resData = $result['data'] ?? [];
    return response([
        'status' => $result['status'] ?? false,
        'message' => $result['message'] ?? 'Paystack request failed',
        'data1' => $resData["recipient"]["recipient_code"] ?? null,
        'data2' => $resData["status"] ?? null,
    ], 200);
}


public function create_refund(Request $request){

    $data=$request->all();
        
    $validator = Validator::make($request->all(), [

            'transaction' => 'required',

        ]);

    if ($validator->fails()) {
        return response(['Validation errors' => $validator->errors()->all()], 422);
    }
   
  $payment = Payment::where('reference', $data['transaction'])
      ->orWhere('code', $data['transaction'])
      ->first();
  if (!$payment) {
      return response(['message' => 'Payment not found.'], 404);
  }

  $deny = $this->access->denyIfFalse($this->access->canAccessPayment($payment));
  if ($deny) {
      return $deny;
  }

  [$allowed, $reason] = $this->refunds->canRefund($payment);
  if (!$allowed) {
      return response(['message' => $reason], 422);
  }

  $fields = [
    'transaction' => $data['transaction']
  ];
  $result = $this->paystack->request('post', 'refund', $fields);

  if ($result instanceof HttpResponse) {
      return $result;
  }

  $resData = $result['data'] ?? [];
  $reference = $resData["transaction"]["reference"] ?? null;
  if ($reference) {
      $payment = Payment::where('reference', $reference)->first();
      if ($payment && $payment->status === 'paid') {
          $payment->status = 'refund_pending';
          $payment->status_reason = 'refund_requested';
          $payment->save();
      }
  }
  return response([
      'status' => $result['status'] ?? false,
      'message' => $result['message'] ?? 'Paystack request failed',
      'data1' => $reference,
      'data2' => $resData["status"] ?? null,
  ], 200);

}


public function fetch_refund(Request $request){

    $data=$request->all();
        
    $validator = Validator::make($request->all(), [

            'reference' => 'required',

        ]);

    if ($validator->fails()) {
        return response(['Validation errors' => $validator->errors()->all()], 422);
    }

    $endpoint = "refund/".$data['reference'];
    $result = $this->paystack->request('get', $endpoint);

    if ($result instanceof HttpResponse) {
        return $result;
    }

    $resData = $result['data'] ?? [];
    $refundStatus = $resData["status"] ?? null;
    $reference = $resData["transaction"]["reference"] ?? ($resData["reference"] ?? null);
    if ($reference) {
        $payment = Payment::where('reference', $reference)->first();
        if ($payment) {
            if ($refundStatus === 'processed') {
                $payment->status = 'refunded';
                $payment->refunded_at = $payment->refunded_at ?? now();
            } elseif ($refundStatus === 'processing') {
                $payment->status = 'refund_pending';
            } elseif ($refundStatus === 'failed') {
                $payment->status = 'paid';
                $payment->status_reason = 'refund_failed';
            }
            $payment->save();
        }
    }
    return response([
        'status' => $result['status'] ?? false,
        'message' => $result['message'] ?? 'Paystack request failed',
        'data1' => $refundStatus,
    ], 200);



}
}
