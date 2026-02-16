<?php

namespace App\Http\Controllers;

use App\Models\CommConsent;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CommConsentController extends Controller
{
    private AccessService $access;

    public function __construct(AccessService $access)
    {
        $this->access = $access;
    }

    public function index()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $consents = CommConsent::where('user_id', $user->id)->orderBy('id', 'desc')->get();
        return response()->json(['data' => $consents], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'channel' => 'nullable|string',
            'scope' => 'nullable|string',
            'version' => 'nullable|string',
            'consultation_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response(['Validation errors' => $validator->errors()->all()], 422);
        }

        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $validator->validated();
        $consent = CommConsent::create([
            'user_id' => $user->id,
            'role' => $this->currentRole(),
            'channel' => $data['channel'] ?? 'whatsapp',
            'scope' => $data['scope'] ?? 'communication',
            'version' => $data['version'] ?? null,
            'consultation_id' => $data['consultation_id'] ?? null,
            'consented_at' => now(),
        ]);

        return response()->json(['data' => $consent], 200);
    }

    public function storePlatform(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'version' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response(['Validation errors' => $validator->errors()->all()], 422);
        }

        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $validator->validated();
        $consent = CommConsent::create([
            'user_id' => $user->id,
            'role' => $this->currentRole(),
            'channel' => 'app',
            'scope' => 'platform',
            'version' => $data['version'] ?? null,
            'consented_at' => now(),
        ]);

        return response()->json(['data' => $consent], 200);
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
