<?php

namespace App\Http\Middleware;

use App\Models\PhiAccessLog;
use App\Services\AccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PhiAccessLogMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (!config('compliance.phi_access_log_enabled', true)) {
            return $response;
        }

        if ($this->isExempt($request->path())) {
            return $response;
        }

        $user = Auth::user();
        $role = $this->currentRole(app(AccessService::class));
        $route = $request->path();
        $targetId = $this->resolveTargetId($request);
        $targetType = $this->resolveTargetType($request);

        PhiAccessLog::create([
            'user_id' => $user?->id,
            'role' => $role,
            'route' => $route,
            'method' => $request->method(),
            'target_type' => $targetType,
            'target_id' => $targetId ? (string) $targetId : null,
            'accessed_at' => now(),
            'metadata' => [
                'query' => $request->query(),
                'route_params' => $request->route()?->parameters() ?? [],
                'status' => $response->getStatusCode(),
            ],
        ]);

        return $response;
    }

    private function currentRole(AccessService $access): string
    {
        if ($access->currentPatientId()) return 'patient';
        if ($access->currentCarerId()) return 'carer';
        if ($access->currentHospitalId()) return 'hospital';
        return 'unknown';
    }

    private function resolveTargetId(Request $request): ?string
    {
        $targetId = $request->route('id')
            ?? $request->route('patient')
            ?? $request->route('labresult')
            ?? $request->route('payment')
            ?? $request->route('transfer');

        if ($targetId) {
            return (string) $targetId;
        }

        $params = $request->route()?->parameters() ?? [];
        if (!empty($params)) {
            $first = reset($params);
            return $first ? (string) $first : null;
        }

        return null;
    }

    private function resolveTargetType(Request $request): string
    {
        $route = $request->route();
        if (!$route) {
            return 'unknown';
        }

        if ($route->getName()) {
            return $route->getName();
        }

        $action = $route->getActionName();
        if (is_string($action) && $action !== 'Closure') {
            return $action;
        }

        return 'unknown';
    }

    private function isExempt(string $path): bool
    {
        $exempt = config('compliance.phi_access_log_exempt_routes', []);
        foreach ($exempt as $pattern) {
            if ($pattern && Str::is($pattern, $path)) {
                return true;
            }
        }
        return false;
    }
}
