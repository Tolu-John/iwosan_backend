<?php

namespace App\Http\Controllers;

use App\Models\CommEvent;
use App\Models\CommProviderLink;
use App\Models\CommThread;
use App\Models\VisitLocation;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class WhatsappWebhookController extends Controller
{
    public function handle(Request $request, WhatsappService $whatsapp)
    {
        $raw = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');
        $signatureStatus = $whatsapp->verifySignature($raw, $signature) ? 'valid' : 'invalid';
        if ($signatureStatus === 'invalid') {
            CommEvent::create([
                'direction' => 'inbound',
                'event_type' => 'signature_invalid',
                'event_timestamp' => Carbon::now(),
                'signature_status' => 'invalid',
                'metadata' => [
                    'signature' => $signature,
                ],
            ]);
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $payload = $request->all();

        $message = data_get($payload, 'entry.0.changes.0.value.messages.0');
        $statuses = data_get($payload, 'entry.0.changes.0.value.statuses.0');
        $waId = $message ? data_get($message, 'from') : null;
        $providerMessageId = $message ? data_get($message, 'id') : null;
        $type = $message ? data_get($message, 'type') : null;
        $timestamp = $message ? data_get($message, 'timestamp') : null;

        $threadId = null;
        if ($waId) {
            $link = CommProviderLink::where('wa_id', $waId)->first();
            $threadId = $link?->thread_id;
        }

        if ($statuses) {
            $statusMessageId = data_get($statuses, 'id');
            $statusValue = data_get($statuses, 'status');
            if ($statusMessageId && $statusValue) {
                $event = CommEvent::where('provider_message_id', $statusMessageId)->first();
                if ($event) {
                    $event->delivery_status = $statusValue;
                    $event->save();
                    return response()->json(['status' => 'updated'], 200);
                }
            }
        }

        if ($providerMessageId) {
            $exists = CommEvent::where('provider_message_id', $providerMessageId)->exists();
            if ($exists) {
                return response()->json(['status' => 'duplicate'], 200);
            }
        }

        $event = CommEvent::create([
            'thread_id' => $threadId,
            'direction' => 'inbound',
            'event_type' => $type ?? 'webhook',
            'provider_message_id' => $providerMessageId,
            'event_timestamp' => $timestamp ? Carbon::createFromTimestamp((int) $timestamp) : Carbon::now(),
            'signature_status' => $signatureStatus,
            'metadata' => $payload,
        ]);

        if ($threadId) {
            $thread = CommThread::find($threadId);
            if ($thread) {
                $thread->last_message_at = $event->event_timestamp;
                $thread->save();
            }
        }

        if ($threadId && $type === 'location') {
            $thread = CommThread::find($threadId);
            if ($thread && $thread->consultation_id) {
                $location = data_get($message, 'location');
                VisitLocation::create([
                    'consultation_id' => $thread->consultation_id,
                    'lat' => data_get($location, 'latitude'),
                    'lng' => data_get($location, 'longitude'),
                    'address' => data_get($location, 'address'),
                    'source' => 'whatsapp',
                    'metadata' => $location,
                ]);
            }
        }

        return response()->json(['status' => 'ok', 'event_id' => $event->id], 200);
    }
}
