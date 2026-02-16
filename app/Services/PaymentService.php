<?php

namespace App\Services;

use App\Models\Carer;
use App\Models\Payment;
use App\Models\PaymentAuditLog;
use Carbon\Carbon;

class PaymentService
{
    private const ALLOWED_STATUSES = [
        'pending',
        'processing',
        'paid',
        'failed',
        'cancelled',
        'refund_pending',
        'refunded',
    ];

    public function create(array $data, AccessService $access, bool $allowPaid = false): Payment
    {
        $this->assertAccess($data, $access);
        $this->assertStatusAllowed($data['status']);
        $this->assertPaidRequiresVerification($data, $allowPaid);

        $payment = new Payment();
        $this->fillPayment($payment, $data);

        if ($data['status'] === 'paid' && !empty($data['verified'])) {
            $payment->verified_at = Carbon::now();
        }

        $payment->save();
        $this->logStatusChange($payment, null, $payment->status, 'api', $payment->status_reason);

        return $payment;
    }

    public function update(Payment $payment, array $data, AccessService $access, bool $allowPaid = false): Payment
    {
        $this->assertAccess($data, $access);

        if ((int) $payment->patient_id !== (int) $data['patient_id']) {
            abort(403, 'Forbidden');
        }

        if ((int) $payment->carer_id !== (int) $data['carer_id']) {
            abort(403, 'Forbidden');
        }

        if ((string) $payment->type !== (string) $data['type']) {
            abort(422, 'Payment type cannot be changed.');
        }

        if ((int) $payment->type_id !== (int) $data['type_id']) {
            abort(422, 'Payment type_id cannot be changed.');
        }

        $this->assertStatusAllowed($data['status']);
        $this->assertStatusTransitionAllowed($payment->status, $data['status']);
        $this->assertPaidRequiresVerification($data, $allowPaid);

        $this->fillPayment($payment, $data);

        $fromStatus = $payment->getOriginal('status');
        if ($data['status'] === 'paid' && !empty($data['verified'])) {
            $payment->verified_at = $payment->verified_at ?? Carbon::now();
        }

        $payment->save();
        $this->logStatusChange($payment, $fromStatus, $payment->status, 'api', $payment->status_reason);

        return $payment;
    }

    private function fillPayment(Payment $payment, array $data): void
    {
        $payment->patient_id = $data['patient_id'];
        $payment->carer_id = $data['carer_id'];
        $payment->code = $data['code'];
        $payment->method = $data['method'];
        $payment->status = $data['status'];
        $payment->type = $data['type'];
        $payment->type_id = $data['type_id'];
        $payment->price = $data['price'];
        $payment->reuse = $data['reuse'] ?? $payment->reuse;
        $payment->reference = $data['reference'] ?? $payment->reference;
        $payment->gateway = $data['gateway'] ?? $payment->gateway;
        $payment->status_reason = $data['status_reason'] ?? $payment->status_reason;
        $payment->currency = $data['currency'] ?? $payment->currency;
        $payment->fees = $data['fees'] ?? $payment->fees;
        $payment->channel = $data['channel'] ?? $payment->channel;
        $payment->gateway_transaction_id = $data['gateway_transaction_id'] ?? $payment->gateway_transaction_id;
    }

    private function assertAccess(array $data, AccessService $access): void
    {
        $currentPatientId = $access->currentPatientId();
        if ($currentPatientId && (int) $data['patient_id'] !== (int) $currentPatientId) {
            abort(403, 'Forbidden');
        }

        $currentCarerId = $access->currentCarerId();
        if ($currentCarerId && (int) $data['carer_id'] !== (int) $currentCarerId) {
            abort(403, 'Forbidden');
        }

        $currentHospitalId = $access->currentHospitalId();
        if ($currentHospitalId) {
            $carerIds = Carer::where('hospital_id', $currentHospitalId)->pluck('id');
            if (!$carerIds->contains($data['carer_id'])) {
                abort(403, 'Forbidden');
            }
        }
    }

    private function assertStatusAllowed(string $status): void
    {
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            abort(422, 'Invalid payment status.');
        }
    }

    private function assertStatusTransitionAllowed(string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }

        $allowed = [
            'pending' => ['processing', 'paid', 'failed', 'cancelled'],
            'processing' => ['paid', 'failed', 'cancelled'],
            'paid' => ['refund_pending', 'refunded'],
            'refund_pending' => ['refunded'],
            'failed' => [],
            'cancelled' => [],
            'refunded' => [],
        ];

        $next = $allowed[$from] ?? [];
        if (!in_array($to, $next, true)) {
            abort(422, 'Invalid payment status transition.');
        }
    }

    private function assertPaidRequiresVerification(array $data, bool $allowPaid): void
    {
        if ($data['status'] !== 'paid') {
            return;
        }

        if (!$allowPaid) {
            abort(403, 'Only the payment processor can mark payments as paid.');
        }

        if (empty($data['verified']) || empty($data['reference'])) {
            abort(422, 'Payment must be verified with a reference before marking as paid.');
        }
    }

    public function logStatusChange(Payment $payment, ?string $from, string $to, string $source, ?string $reason = null, ?int $createdBy = null, ?array $metadata = null): void
    {
        if ($from === $to) {
            return;
        }

        PaymentAuditLog::create([
            'payment_id' => $payment->id,
            'from_status' => $from,
            'to_status' => $to,
            'source' => $source,
            'reason' => $reason,
            'metadata' => $metadata,
            'created_by' => $createdBy,
        ]);
    }
}
