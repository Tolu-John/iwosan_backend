<?php

namespace App\Http\Controllers;

use App\Models\CommParticipant;
use App\Models\CommThread;
use App\Models\Consultation;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CommThreadController extends Controller
{
    private AccessService $access;

    public function __construct(AccessService $access)
    {
        $this->access = $access;
    }

    public function index(Request $request)
    {
        $consultationId = $request->query('consultation_id');
        if (!$consultationId) {
            return response()->json(['message' => 'consultation_id is required'], 422);
        }

        $consultation = Consultation::find($consultationId);
        if (!$consultation) {
            return response()->json(['message' => 'Consultation not found'], 404);
        }
        if (!$this->access->canAccessConsultation($consultation)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $threads = CommThread::where('consultation_id', $consultationId)->get();
        return response()->json(['data' => $threads], 200);
    }

    public function show($id)
    {
        $thread = CommThread::with('participants')->find($id);
        if (!$thread) {
            return response()->json(['message' => 'Thread not found'], 404);
        }

        if ($thread->consultation_id) {
            $consultation = Consultation::find($thread->consultation_id);
            if ($consultation && !$this->access->canAccessConsultation($consultation)) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        return response()->json(['data' => $thread], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'consultation_id' => 'required|integer',
            'channel' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response(['Validation errors' => $validator->errors()->all()], 422);
        }

        $data = $validator->validated();
        $consultation = Consultation::find($data['consultation_id']);
        if (!$consultation) {
            return response()->json(['message' => 'Consultation not found'], 404);
        }
        if (!$this->access->canAccessConsultation($consultation)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $channel = $data['channel'] ?? 'whatsapp';
        $thread = CommThread::where('consultation_id', $consultation->id)
            ->where('channel', $channel)
            ->first();

        if (!$thread) {
            $user = Auth::user();
            $thread = CommThread::create([
                'consultation_id' => $consultation->id,
                'channel' => $channel,
                'status' => 'active',
                'created_by_user_id' => $user?->id,
                'created_by_role' => $this->currentRole(),
            ]);
        }

        $this->ensureParticipant($thread, Auth::user());

        return response()->json(['data' => $thread->load('participants')], 200);
    }

    private function ensureParticipant(CommThread $thread, $user): void
    {
        if (!$user) {
            return;
        }
        $exists = CommParticipant::where('thread_id', $thread->id)
            ->where('user_id', $user->id)
            ->exists();
        if ($exists) {
            return;
        }
        CommParticipant::create([
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'role' => $this->currentRole(),
        ]);
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
