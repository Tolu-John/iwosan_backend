<?php

namespace App\Http\Controllers;

use App\Models\DisclosureRequest;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DisclosureRequestController extends Controller
{
    private AccessService $access;

    public function __construct(AccessService $access)
    {
        $this->access = $access;
    }

    public function index(Request $request)
    {
        $this->assertHospital();

        $query = DisclosureRequest::query()->orderBy('id', 'desc');
        if ($request->query('status')) {
            $query->where('status', $request->query('status'));
        }

        $perPage = (int) $request->query('per_page', 50);
        return response()->json($query->paginate($perPage), 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scope' => 'nullable|string',
            'resource' => 'nullable|string',
            'filters' => 'nullable|array',
            'justification' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response(['Validation errors' => $validator->errors()->all()], 422);
        }

        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $validator->validated();
        $requestModel = DisclosureRequest::create([
            'requested_by' => $user->id,
            'role' => $this->currentRole(),
            'scope' => $data['scope'] ?? 'export',
            'resource' => $data['resource'] ?? null,
            'filters' => $data['filters'] ?? null,
            'justification' => $data['justification'],
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        return response()->json(['data' => $requestModel], 200);
    }

    public function approve(Request $request, $id)
    {
        $this->assertHospital();

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:approved,rejected',
            'review_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response(['Validation errors' => $validator->errors()->all()], 422);
        }

        $user = Auth::user();
        $data = $validator->validated();

        $requestModel = DisclosureRequest::findOrFail($id);
        $requestModel->status = $data['status'];
        $requestModel->review_notes = $data['review_notes'] ?? null;
        $requestModel->approved_by = $user?->id;
        $requestModel->approved_at = now();
        $requestModel->save();

        return response()->json(['data' => $requestModel], 200);
    }

    private function assertHospital(): void
    {
        if (!$this->access->currentHospitalId()) {
            abort(403, 'Forbidden');
        }
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
