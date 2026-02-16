<?php

namespace App\Http\Controllers;

use App\Models\PhiAccessLog;
use App\Models\SecurityIncident;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplianceAuditController extends Controller
{
    private AccessService $access;

    public function __construct(AccessService $access)
    {
        $this->access = $access;
    }

    public function phiAccessLogs(Request $request)
    {
        $this->assertHospital();

        $query = PhiAccessLog::query()->orderBy('id', 'desc');
        $this->applyDateFilters($query, $request);

        if ($request->query('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        $perPage = (int) $request->query('per_page', 50);
        return response()->json($query->paginate($perPage), 200);
    }

    public function securityIncidents(Request $request)
    {
        $this->assertHospital();

        $query = SecurityIncident::query()->orderBy('id', 'desc');
        $this->applyDateFilters($query, $request);

        if ($request->query('severity')) {
            $query->where('severity', $request->query('severity'));
        }
        if ($request->query('status')) {
            $query->where('status', $request->query('status'));
        }

        $perPage = (int) $request->query('per_page', 50);
        return response()->json($query->paginate($perPage), 200);
    }

    public function exportPhiAccessLogs(Request $request)
    {
        $this->assertHospital();

        $query = PhiAccessLog::query()->orderBy('id', 'desc');
        $this->applyDateFilters($query, $request);

        return $this->streamCsv('phi_access_logs.csv', $query->cursor(), [
            'id', 'user_id', 'role', 'route', 'method', 'target_type', 'target_id', 'accessed_at', 'created_at',
        ], function ($row) {
            return [
                $row->id,
                $row->user_id,
                $row->role,
                $row->route,
                $row->method,
                $row->target_type,
                $row->target_id,
                $row->accessed_at,
                $row->created_at,
            ];
        });
    }

    public function exportSecurityIncidents(Request $request)
    {
        $this->assertHospital();

        $query = SecurityIncident::query()->orderBy('id', 'desc');
        $this->applyDateFilters($query, $request);

        return $this->streamCsv('security_incidents.csv', $query->cursor(), [
            'id', 'user_id', 'type', 'severity', 'status', 'route', 'ip', 'created_at',
        ], function ($row) {
            return [
                $row->id,
                $row->user_id,
                $row->type,
                $row->severity,
                $row->status,
                $row->route,
                $row->ip,
                $row->created_at,
            ];
        });
    }

    public function encryptionStatus()
    {
        $this->assertHospital();

        return response()->json([
            'provider_enforced' => (bool) config('security.encryption_provider_enforced'),
            'provider_name' => config('security.encryption_provider_name'),
            'field_level_enabled' => (bool) config('security.encryption_field_level'),
            'key_id' => config('security.encryption_key_id'),
            'key_rotation_days' => (int) config('security.encryption_key_rotation_days', 365),
        ], 200);
    }

    private function applyDateFilters($query, Request $request): void
    {
        if ($request->query('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }
        if ($request->query('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }
    }

    private function streamCsv(string $filename, $rows, array $header, callable $map): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $header, $map) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $header);
            foreach ($rows as $row) {
                fputcsv($handle, $map($row));
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function assertHospital(): void
    {
        $user = Auth::user();
        if (!$user || !$this->access->currentHospitalId()) {
            abort(403, 'Forbidden');
        }
    }
}
