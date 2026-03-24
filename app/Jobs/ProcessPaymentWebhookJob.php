<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Services\PaymentService;
use App\Services\TeletestWorkflowService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPaymentWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $eventId;

    public function __construct(int $eventId)
    {
        $this->eventId = $eventId;
    }

    public function handle(PaymentService $payments, TeletestWorkflowService $teletestWorkflow): void
    {
        $eventLog = PaymentWebhookEvent::find($this->eventId);
        if (!$eventLog || $eventLog->processed_at) {
            return;
        }

        $payload = $eventLog->payload ?? [];
        $data = $payload['data'] ?? [];
        $reference = $eventLog->reference;
        $event = $eventLog->event;

        $payment = Payment::where('reference', $reference)
            ->orWhere('code', $reference)
            ->first();

        if (!$payment) {
            Log::warning('Paystack webhook reference not found.', ['reference' => $reference, 'event' => $event]);
            Log::channel('alerts')->error('payment.webhook.missing_reference', [
                'reference' => $reference,
                'event' => $event,
            ]);
            return;
        }

        $status = $this->mapStatus($event, $data['status'] ?? null);
        if (!$status) {
            $eventLog->processed_at = Carbon::now();
            $eventLog->save();
            return;
        }

        $fromStatus = $payment->status;
        $payment->status = $status;
        $payment->gateway = 'paystack';
        $payment->reference = $reference;
        $payment->status_reason = 'webhook:'.$event;
        $payment->gateway_transaction_id = $eventLog->event_id;
        $payment->channel = $data['channel'] ?? $payment->channel;
        $payment->currency = $data['currency'] ?? $payment->currency;
        $payment->fees = isset($data['fees']) ? (int) $data['fees'] : $payment->fees;
        $payment->gateway_payload = $data ?: $payment->gateway_payload;

        if ($status === 'paid') {
            $payment->verified_at = $payment->verified_at ?? Carbon::now();
            $payment->paid_at = $payment->paid_at ?? Carbon::now();
        }
        if ($status === 'processing') {
            $payment->processing_at = $payment->processing_at ?? Carbon::now();
        }
        if ($status === 'failed') {
            $payment->failed_at = $payment->failed_at ?? Carbon::now();
        }
        if ($status === 'refunded') {
            $payment->refunded_at = $payment->refunded_at ?? Carbon::now();
        }

        $payment->save();
        $payments->logStatusChange($payment, $fromStatus, $status, 'webhook', $payment->status_reason, null, [
            'event' => $event,
            'gateway' => 'paystack',
        ]);

        $this->syncTeletestWorkflowFromPayment($payment, $status, $teletestWorkflow);

        $eventLog->processed_at = Carbon::now();
        $eventLog->save();
    }

    private function mapStatus(string $event, ?string $gatewayStatus): ?string
    {
        if ($event === 'charge.success') {
            return 'paid';
        }

        if ($event === 'charge.failed') {
            return 'failed';
        }

        if (str_starts_with($event, 'refund.')) {
            return $gatewayStatus === 'failed' ? 'refund_pending' : 'refunded';
        }

        if (in_array($event, ['charge.pending', 'charge.created'], true)) {
            return 'processing';
        }

        return null;
    }

    private function syncTeletestWorkflowFromPayment(Payment $payment, string $paymentStatus, TeletestWorkflowService $workflow): void
    {
        if ((string) $payment->type !== 'teletest' || !(int) $payment->type_id) {
            return;
        }

        $teletest = \App\Models\Teletest::find((int) $payment->type_id);
        if (!$teletest) {
            return;
        }

        try {
            if ($paymentStatus === 'paid' && in_array((string) $teletest->status, ['awaiting_payment', 'payment_failed'], true)) {
                $workflow->transitionSystem(
                    $teletest,
                    'scheduled',
                    'payment_verified',
                    'Payment verified via webhook.'
                );
                return;
            }

            if ($paymentStatus === 'failed' && (string) $teletest->status === 'awaiting_payment') {
                $workflow->transitionSystem(
                    $teletest,
                    'payment_failed',
                    'payment_failed',
                    'Payment attempt failed via webhook.'
                );
                return;
            }

            if ($paymentStatus === 'refund_pending' && (string) $teletest->status === 'cancelled_by_hospital') {
                $workflow->transitionSystem(
                    $teletest,
                    'payment_refund_pending',
                    'refund_started',
                    'Refund initiated via webhook.'
                );
                return;
            }

            if ($paymentStatus === 'refunded' && (string) $teletest->status === 'payment_refund_pending') {
                $workflow->transitionSystem(
                    $teletest,
                    'payment_refunded',
                    'refund_completed',
                    'Refund completed via webhook.'
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Failed syncing teletest workflow from payment webhook.', [
                'payment_id' => $payment->id,
                'teletest_id' => $teletest->id,
                'status' => $paymentStatus,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
