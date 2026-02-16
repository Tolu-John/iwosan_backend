<?php

namespace App\Http\Middleware;

use App\Services\AccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RequestContextMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $requestId = (string) $request->header('X-Request-Id');
        if ($requestId === '') {
            $requestId = (string) Str::uuid();
        }

        $access = app(AccessService::class);
        $actorRole = null;
        $actorId = null;

        if ($access->currentPatientId()) {
            $actorRole = 'patient';
            $actorId = $access->currentPatientId();
        } elseif ($access->currentCarerId()) {
            $actorRole = 'carer';
            $actorId = $access->currentCarerId();
        } elseif ($access->currentHospitalId()) {
            $actorRole = 'hospital';
            $actorId = $access->currentHospitalId();
        } elseif ($request->user()) {
            $actorRole = 'user';
            $actorId = $request->user()->id;
        }

        $request->attributes->set('request_id', $requestId);
        Log::withContext([
            'request_id' => $requestId,
            'actor_role' => $actorRole,
            'actor_id' => $actorId,
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
        ]);

        $start = microtime(true);
        $response = $next($request);
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        Log::info('request.completed', [
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
        ]);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
