<?php

namespace App\Http\Controllers;

use App\Models\SecurityIncident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SecurityIncidentController extends Controller
{
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:open,investigating,resolved',
            'response_notes' => 'nullable|string',
            'assigned_to' => 'nullable|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response(['Validation errors' => $validator->errors()->all()], 422);
        }

        $data = $validator->validated();

        $incident = SecurityIncident::findOrFail($id);
        $incident->status = $data['status'];
        if (array_key_exists('response_notes', $data)) {
            $incident->response_notes = $data['response_notes'];
        }
        if (array_key_exists('assigned_to', $data)) {
            $incident->assigned_to = $data['assigned_to'];
        }
        $incident->resolved_at = $data['status'] === 'resolved' ? now() : null;
        $incident->save();

        return response()->json(['data' => $incident], 200);
    }
}
