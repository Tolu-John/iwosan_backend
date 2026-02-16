<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\PhiAccessLog;
use App\Services\AccessService;

class ExportJustificationMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $justification = $request->header('X-Disclosure-Justification');
        $disclosureRequestId = $request->header('X-Disclosure-Request-Id') ?? $request->query('disclosure_request_id');
        if (!$justification || trim($justification) === '') {
            return response()->json(['message' => 'Disclosure justification required'], 422);
        }

        $response = $next($request);

        $user = Auth::user();
        $role = $this->currentRole(app(AccessService::class));
        PhiAccessLog::create([
            'user_id' => $user?->id,
            'role' => $role,
            'route' => $request->path(),
            'method' => $request->method(),
            'target_type' => 'export',
            'target_id' => null,
            'metadata' => [
                'justification' => $justification,
                'disclosure_request_id' => $disclosureRequestId,
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
}
