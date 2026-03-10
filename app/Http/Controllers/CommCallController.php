<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\CommEvent;
use App\Models\CommThread;
use App\Models\Consultation;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CommCallController extends Controller
{
    private AccessService $access;

    public function __construct(AccessService $access)
    {
        $this->access = $access;
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'thread_id' => 'nullable|integer',
            'consultation_id' => 'nullable|integer',
            'appointment_id' => 'nullable|integer',
            'type' => 'required|string|in:voice,video',
        ]);

        if ($validator->fails()) {
            return response(['Validation errors' => $validator->errors()->all()], 422);
        }

        $data = $validator->validated();
        $thread = null;
        $appointment = null;

        if (!empty($data['thread_id'])) {
            $thread = CommThread::find($data['thread_id']);
            if (!$thread) {
                return response()->json(['message' => 'Thread not found'], 404);
            }
        } elseif (!empty($data['consultation_id'])) {
            $consultation = Consultation::find($data['consultation_id']);
            if (!$consultation) {
                return response()->json(['message' => 'Consultation not found'], 404);
            }
            if (!$this->access->canAccessConsultation($consultation)) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
                $thread = CommThread::firstOrCreate(
                    ['consultation_id' => $consultation->id, 'channel' => 'whatsapp'],
                    [
                        'status' => 'active',
                        'created_by_user_id' => Auth::id(),
                        'created_by_role' => $this->currentRole(),
                    ]
                );
        } elseif (!empty($data['appointment_id'])) {
            $appointment = Appointment::find($data['appointment_id']);
            if (!$appointment) {
                return response()->json(['message' => 'Appointment not found'], 404);
            }
            if (!$this->access->canAccessAppointment($appointment)) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
            $thread = CommThread::firstOrCreate(
                [
                    'consultation_id' => null,
                    'channel' => 'whatsapp',
                    'provider_thread_id' => 'appointment:' . $appointment->id,
                ],
                [
                    'status' => 'active',
                    'created_by_user_id' => Auth::id(),
                    'created_by_role' => $this->currentRole(),
                ]
            );
        } else {
            return response()->json(['message' => 'thread_id, consultation_id or appointment_id is required'], 422);
        }

        if ($thread->consultation_id) {
            $consultation = Consultation::find($thread->consultation_id);
            if ($consultation && !$this->access->canAccessConsultation($consultation)) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $event = CommEvent::create([
            'thread_id' => $thread->id,
            'direction' => 'outbound',
            'event_type' => 'call_intent',
            'sender_role' => $this->currentRole(),
            'event_timestamp' => Carbon::now(),
            'metadata' => [
                'type' => $data['type'],
                'appointment_id' => $appointment?->id,
            ],
        ]);

        return response()->json(['data' => $event, 'thread_id' => $thread->id], 200);
    }

    private function currentRole(): string
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
        return 'unknown';
    }
}
