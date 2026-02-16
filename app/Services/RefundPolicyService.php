<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Payment;
use App\Models\Teletest;
use Illuminate\Support\Carbon;

class RefundPolicyService
{
    private AccessService $access;

    public function __construct(AccessService $access)
    {
        $this->access = $access;
    }

    public function canRefund(Payment $payment): array
    {
        if ($payment->status !== 'paid') {
            return [false, 'Only paid payments can be refunded.'];
        }

        if ($this->access->currentHospitalId() && config('refunds.hospital_override')) {
            return [true, null];
        }

        if (!$this->access->currentPatientId()) {
            return [false, 'Only patients can request refunds.'];
        }

        $windowHours = (int) config('refunds.patient_refund_hours', 24);
        $cancelHours = (int) config('refunds.patient_cancel_hours', 6);

        [$scheduledAt, $typeLabel] = $this->resolveScheduledAt($payment);
        if (!$scheduledAt) {
            return [false, 'Unable to determine scheduled time for refund policy.'];
        }

        $now = Carbon::now();
        if ($now->greaterThan($scheduledAt->copy()->subHours($cancelHours))) {
            return [false, 'Refund window has closed for this booking.'];
        }

        if ($payment->paid_at && $now->greaterThan($payment->paid_at->copy()->addHours($windowHours))) {
            return [false, 'Refund window has expired.'];
        }

        return [true, null];
    }

    private function resolveScheduledAt(Payment $payment): array
    {
        $type = $payment->type;
        $typeId = $payment->type_id;

        if ($type === 'appointment') {
            $appointment = Appointment::find($typeId);
            return [$this->parseDateTime($appointment?->date_time), 'appointment'];
        }

        if ($type === 'consultation') {
            $consultation = Consultation::find($typeId);
            return [$this->parseDateTime($consultation?->date_time), 'consultation'];
        }

        if ($type === 'teletest') {
            $teletest = Teletest::find($typeId);
            return [$this->parseDateTime($teletest?->date_time), 'teletest'];
        }

        return [null, null];
    }

    private function parseDateTime(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
