<?php

namespace App\Http\Controllers;

use App\Models\PaymentWebhookEvent;
use App\Jobs\ProcessPaymentWebhookJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $secret = config('paystack.secret');
        if (!$secret) {
            return response(['message' => 'Paystack secret not configured.'], 500);
        }

        $signature = $request->header('x-paystack-signature');
        $payload = $request->getContent();
        $expected = hash_hmac('sha512', $payload, $secret);

        if (!$signature || !hash_equals($expected, $signature)) {
            return response(['message' => 'Invalid signature.'], 401);
        }

        $event = (string) $request->input('event');
        $data = $request->input('data', []);
        $reference = $data['reference'] ?? null;
        $eventId = isset($data['id']) ? (string) $data['id'] : null;

        if (!$reference) {
            Log::warning('Paystack webhook missing reference.', ['event' => $event]);
            return response(['message' => 'Missing reference.'], 422);
        }

        $existingEvent = PaymentWebhookEvent::where('gateway', 'paystack')
            ->where('event', $event)
            ->where('reference', $reference)
            ->first();
        if ($existingEvent) {
            return response(['message' => 'Already processed.'], 200);
        }

        $eventLog = PaymentWebhookEvent::create([
            'gateway' => 'paystack',
            'event' => $event,
            'reference' => $reference,
            'event_id' => $eventId,
            'payload' => $request->input(),
            'signature' => $signature,
        ]);

        ProcessPaymentWebhookJob::dispatch($eventLog->id);

        return response(['message' => 'Accepted'], 202);
    }
}
