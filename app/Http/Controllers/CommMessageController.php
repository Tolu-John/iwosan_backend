<?php

namespace App\Http\Controllers;

use App\Models\CommEvent;
use App\Models\CommParticipant;
use App\Models\CommTemplate;
use App\Models\CommThread;
use App\Models\Consultation;
use App\Services\AccessService;
use App\Services\NotificationFallbackService;
use App\Services\SmsFallbackService;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class CommMessageController extends Controller
{
    private AccessService $access;

    public function __construct(AccessService $access)
    {
        $this->access = $access;
    }

    public function index(Request $request)
    {
        $threadId = $request->query('thread_id');
        if (!$threadId) {
            return response()->json(['message' => 'thread_id is required'], 422);
        }

        $thread = CommThread::find($threadId);
        if (!$thread) {
            return response()->json(['message' => 'Thread not found'], 404);
        }

        if ($thread->consultation_id) {
            $consultation = Consultation::find($thread->consultation_id);
            if ($consultation && !$this->access->canAccessConsultation($consultation)) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $events = CommEvent::where('thread_id', $thread->id)
            ->orderBy('event_timestamp', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(['data' => $events], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'thread_id' => 'required|integer',
            'direction' => 'nullable|string',
            'event_type' => 'nullable|string',
            'provider_message_id' => 'nullable|string',
            'sender_role' => 'nullable|string',
            'delivery_status' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response(['Validation errors' => $validator->errors()->all()], 422);
        }

        $data = $validator->validated();
        $thread = CommThread::find($data['thread_id']);
        if (!$thread) {
            return response()->json(['message' => 'Thread not found'], 404);
        }

        if ($thread->consultation_id) {
            $consultation = Consultation::find($thread->consultation_id);
            if ($consultation && !$this->access->canAccessConsultation($consultation)) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $event = CommEvent::create([
            'thread_id' => $thread->id,
            'direction' => $data['direction'] ?? 'outbound',
            'event_type' => $data['event_type'] ?? 'message',
            'provider_message_id' => $data['provider_message_id'] ?? null,
            'sender_role' => $data['sender_role'] ?? null,
            'delivery_status' => $data['delivery_status'] ?? null,
            'event_timestamp' => Carbon::now(),
            'metadata' => $data['metadata'] ?? null,
        ]);

        $thread->last_message_at = $event->event_timestamp;
        $thread->save();

        return response()->json(['data' => $event], 200);
    }

    public function send(Request $request, WhatsappService $whatsapp, NotificationFallbackService $fallback, SmsFallbackService $sms)
    {
        $validator = Validator::make($request->all(), [
            'thread_id' => 'required|integer',
            'to' => 'nullable|string',
            'text' => 'nullable|string',
            'template' => 'nullable|string',
            'language' => 'nullable|string',
            'template_vars' => 'nullable|array',
            'media_type' => 'nullable|string',
            'media_url' => 'nullable|string',
            'caption' => 'nullable|string',
            'fallback_email' => 'nullable|email',
            'fallback_phone' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response(['Validation errors' => $validator->errors()->all()], 422);
        }

        $data = $validator->validated();
        $thread = CommThread::find($data['thread_id']);
        if (!$thread) {
            return response()->json(['message' => 'Thread not found'], 404);
        }

        if ($thread->consultation_id) {
            $consultation = Consultation::find($thread->consultation_id);
            if ($consultation && !$this->access->canAccessConsultation($consultation)) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $to = $data['to'] ?? $this->resolveRecipient($thread->id);
        if (!$to) {
            return response()->json(['message' => 'Recipient not found for thread'], 422);
        }

        $templateComponents = null;
        if (!empty($data['template'])) {
            $template = CommTemplate::where('provider', 'whatsapp')
                ->where('name', $data['template'])
                ->where('language', $data['language'] ?? 'en')
                ->where('active', true)
                ->first();
            if (!$template) {
                return response()->json(['message' => 'Template not registered'], 422);
            }
            $vars = $data['template_vars'] ?? [];
            $required = $template->variables ?? [];
            foreach ($required as $key) {
                if (!array_key_exists($key, $vars)) {
                    return response()->json(['message' => "Missing template var: $key"], 422);
                }
            }
            if (!empty($vars)) {
                $templateComponents = [
                    [
                        'type' => 'body',
                        'parameters' => array_map(function ($value) {
                            return ['type' => 'text', 'text' => (string) $value];
                        }, array_values($vars)),
                    ],
                ];
            }
        }

        if (empty($data['template']) && $this->isSessionExpired($thread)) {
            return response()->json(['message' => 'WhatsApp session expired. Use a template message.'], 422);
        }

        $event = CommEvent::create([
            'thread_id' => $thread->id,
            'direction' => 'outbound',
            'event_type' => !empty($data['template']) ? 'template' : (!empty($data['media_type']) ? 'media' : 'message'),
            'sender_role' => 'system',
            'delivery_status' => 'pending',
            'event_timestamp' => Carbon::now(),
            'metadata' => [
                'to' => $to,
                'template' => $data['template'] ?? null,
                'media_type' => $data['media_type'] ?? null,
            ],
        ]);

        try {
            if (!empty($data['text'])) {
                $response = $whatsapp->sendText($to, $data['text']);
            } elseif (!empty($data['template'])) {
                $response = $whatsapp->sendTemplate(
                    $to,
                    $data['template'],
                    $data['language'] ?? 'en',
                    $templateComponents ?? []
                );
            } elseif (!empty($data['media_type']) && !empty($data['media_url'])) {
                $response = $whatsapp->sendMedia($to, $data['media_type'], $data['media_url'], $data['caption'] ?? null);
            } else {
                return response()->json(['message' => 'Provide text, template, or media payload'], 422);
            }
        } catch (\Throwable $e) {
            if (!empty($data['fallback_email'])) {
                $fallback->sendEmail(
                    $data['fallback_email'],
                    'Iwosan message',
                    $data['text'] ?? 'You have a new message from Iwosan.'
                );
            }
            if (!empty($data['fallback_phone'])) {
                $sms->sendSms(
                    $data['fallback_phone'],
                    $data['text'] ?? 'You have a new message from Iwosan.'
                );
            }
            $event->delivery_status = 'failed';
            $event->save();
            return response()->json(['message' => $e->getMessage()], 500);
        }

        $providerMessageId = data_get($response, 'messages.0.id');
        $event->provider_message_id = $providerMessageId;
        $event->delivery_status = 'sent';
        $event->metadata = array_merge($event->metadata ?? [], ['response' => $response]);
        $event->save();

        $thread->last_message_at = $event->event_timestamp;
        $thread->save();

        return response()->json(['data' => $event], 200);
    }

    private function resolveRecipient(int $threadId): ?string
    {
        $participant = CommParticipant::where('thread_id', $threadId)
            ->whereNotNull('wa_id')
            ->first();
        if ($participant?->wa_id) {
            return $participant->wa_id;
        }

        $participant = CommParticipant::where('thread_id', $threadId)
            ->whereNotNull('phone')
            ->first();
        return $participant?->phone;
    }

    private function isSessionExpired(CommThread $thread): bool
    {
        if (!$thread->last_message_at) {
            return false;
        }
        return $thread->last_message_at->lt(Carbon::now()->subHours(24));
    }
}
