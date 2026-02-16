<?php

namespace App\Http\Middleware;

use App\Models\CommConsent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PlatformConsentMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('compliance.platform_consent_required')) {
            return $next($request);
        }

        if ($this->isExempt($request->path())) {
            return $next($request);
        }

        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $hasConsent = CommConsent::where('user_id', $user->id)
            ->where('scope', 'platform')
            ->orderBy('id', 'desc')
            ->exists();

        if (!$hasConsent) {
            return response()->json(['message' => 'Platform consent required'], 403);
        }

        return $next($request);
    }

    private function isExempt(string $path): bool
    {
        $exempt = config('compliance.platform_consent_exempt_routes', []);
        foreach ($exempt as $pattern) {
            if ($pattern && Str::is($pattern, $path)) {
                return true;
            }
        }
        return false;
    }
}
