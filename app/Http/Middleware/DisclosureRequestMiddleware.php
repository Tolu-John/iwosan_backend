<?php

namespace App\Http\Middleware;

use App\Models\DisclosureRequest;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DisclosureRequestMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $requestId = $request->header('X-Disclosure-Request-Id') ?? $request->query('disclosure_request_id');
        if (!$requestId) {
            return response()->json(['message' => 'Disclosure request ID required'], 422);
        }

        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $requestModel = DisclosureRequest::where('id', $requestId)
            ->where('status', 'approved')
            ->first();

        if (!$requestModel) {
            return response()->json(['message' => 'Disclosure request not approved'], 403);
        }

        if ((int) $requestModel->requested_by !== (int) $user->id) {
            return response()->json(['message' => 'Disclosure request not owned by user'], 403);
        }

        return $next($request);
    }
}
