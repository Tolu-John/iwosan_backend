<?php

namespace App\Http\Middleware;

use App\Models\Carer;
use Closure;
use Illuminate\Http\Request;

class EnsureCarerRole
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $carer = Carer::where('user_id', $user->id)->first();
        if (!$carer) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $routeId = $request->route('id');
        if ($routeId !== null && (int) $routeId !== (int) $carer->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
