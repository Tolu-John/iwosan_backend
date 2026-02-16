<?php

namespace App\Http\Middleware;

use App\Models\Patient;
use Closure;
use Illuminate\Http\Request;

class EnsurePatientRole
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $patient = Patient::where('user_id', $user->id)->first();
        if (!$patient) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $routeId = $request->route('id');
        if ($routeId !== null && (int) $routeId !== (int) $patient->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
