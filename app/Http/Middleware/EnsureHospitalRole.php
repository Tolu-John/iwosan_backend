<?php

namespace App\Http\Middleware;

use App\Models\Hospital;
use Closure;
use Illuminate\Http\Request;

class EnsureHospitalRole
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $hospital = Hospital::where('user_id', $user->id)
            ->orWhere('firedb_id', $user->firedb_id)
            ->first();
        if (!$hospital) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $routeId = $request->route('id');
        if ($routeId !== null && (int) $routeId !== (int) $hospital->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
