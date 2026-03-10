<?php

namespace App\Http\Controllers;

use App\Models\Carer;
use App\Models\HospitalCarerNote;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HospitalCarerNoteController extends Controller
{
    private AccessService $access;

    public function __construct(AccessService $access)
    {
        $this->access = $access;
    }

    public function index(int $carerId)
    {
        $hospitalId = $this->access->currentHospitalId();
        if (!$hospitalId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $carer = Carer::where('id', $carerId)
            ->where('hospital_id', $hospitalId)
            ->first();

        if (!$carer) {
            return response()->json(['message' => 'Carer not found'], 404);
        }

        $notes = HospitalCarerNote::query()
            ->where('hospital_id', $hospitalId)
            ->where('carer_id', $carerId)
            ->with(['author:id,firstname,lastname,email'])
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(function (HospitalCarerNote $note) {
                return [
                    'id' => (int) $note->id,
                    'note' => (string) $note->note,
                    'created_at' => optional($note->created_at)->toISOString(),
                    'updated_at' => optional($note->updated_at)->toISOString(),
                    'author' => [
                        'id' => (int) ($note->author->id ?? 0),
                        'firstname' => (string) ($note->author->firstname ?? ''),
                        'lastname' => (string) ($note->author->lastname ?? ''),
                        'email' => (string) ($note->author->email ?? ''),
                    ],
                ];
            })
            ->values();

        return response()->json([
            'data' => $notes,
            'count' => $notes->count(),
        ]);
    }

    public function store(Request $request, int $carerId)
    {
        $hospitalId = $this->access->currentHospitalId();
        if (!$hospitalId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $carer = Carer::where('id', $carerId)
            ->where('hospital_id', $hospitalId)
            ->first();

        if (!$carer) {
            return response()->json(['message' => 'Carer not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'note' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $note = HospitalCarerNote::create([
            'hospital_id' => $hospitalId,
            'carer_id' => $carerId,
            'created_by_user_id' => optional($request->user())->id,
            'note' => trim((string) $request->input('note')),
        ]);

        $note->loadMissing(['author:id,firstname,lastname,email']);

        return response()->json([
            'message' => 'Note saved.',
            'data' => [
                'id' => (int) $note->id,
                'note' => (string) $note->note,
                'created_at' => optional($note->created_at)->toISOString(),
                'updated_at' => optional($note->updated_at)->toISOString(),
                'author' => [
                    'id' => (int) ($note->author->id ?? 0),
                    'firstname' => (string) ($note->author->firstname ?? ''),
                    'lastname' => (string) ($note->author->lastname ?? ''),
                    'email' => (string) ($note->author->email ?? ''),
                ],
            ],
        ]);
    }
}
