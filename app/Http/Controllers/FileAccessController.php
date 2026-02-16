<?php

namespace App\Http\Controllers;

use App\Models\Certlice;
use App\Models\FileAccessLog;
use App\Models\LabResult;
use App\Models\PhiAccessLog;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class FileAccessController extends Controller
{
    private AccessService $access;

    public function __construct(AccessService $access)
    {
        $this->access = $access;
    }

    public function labResultUrls($id)
    {
        $labResult = LabResult::withTrashed()->find($id);
        if (!$labResult) {
            return response()->json(['message' => 'Lab result not found.'], 404);
        }

        $this->authorize('view', $labResult);

        $filenames = array_values(array_filter([
            $this->filenameFromUrl($labResult->result_picture),
            $this->filenameFromUrl($labResult->result_picture_front),
            $this->filenameFromUrl($labResult->result_picture_back),
        ]));

        $urls = array_map(function ($filename) {
            return URL::temporarySignedRoute(
                'files.labresult',
                now()->addMinutes(10),
                ['filename' => $filename]
            );
        }, $filenames);

        return response([
            'id' => (string) $labResult->id,
            'files' => $urls,
        ], 200);
    }

    public function certliceUrl($id)
    {
        $certlice = Certlice::find($id);
        if (!$certlice) {
            return response()->json(['message' => 'Certificate not found.'], 404);
        }

        $deny = $this->access->denyIfFalse($this->access->canAccessCertlice($certlice, true));
        if ($deny) {
            return $deny;
        }

        if ($this->access->currentPatientId()) {
            if ($certlice->status !== 'verified' || $this->isExpired($certlice)) {
                return response()->json(['message' => 'Not found'], 404);
            }
        }

        $filename = $this->filenameFromUrl($certlice->location);
        if (!$filename) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        $routeName = $certlice->type === 'hospital' ? 'files.certlice.hospital' : 'files.certlice.carer';

        $url = URL::temporarySignedRoute(
            $routeName,
            now()->addMinutes(10),
            ['filename' => $filename]
        );

        return response([
            'id' => (string) $certlice->id,
            'file' => $url,
        ], 200);
    }

    public function serveLabResult(Request $request, $filename)
    {
        return $this->serveFile($request, 'labresult/'.$filename, 'labresult');
    }

    public function serveCertliceHospital(Request $request, $filename)
    {
        return $this->serveFile($request, 'certlices/hospital/'.$filename, 'certlice_hospital');
    }

    public function serveCertliceCarer(Request $request, $filename)
    {
        return $this->serveFile($request, 'certlices/carer/'.$filename, 'certlice_carer');
    }

    private function serveFile(Request $request, string $path, string $type)
    {
        if (!Storage::disk('iwosan_files')->exists($path)) {
            abort(404);
        }

        FileAccessLog::create([
            'file_type' => $type,
            'path' => $path,
            'owner_type' => null,
            'owner_id' => null,
            'user_id' => Auth::id(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        PhiAccessLog::create([
            'user_id' => Auth::id(),
            'role' => $this->access->currentHospitalId() ? 'hospital' : ($this->access->currentCarerId() ? 'carer' : ($this->access->currentPatientId() ? 'patient' : 'unknown')),
            'route' => $request->path(),
            'method' => $request->method(),
            'target_type' => 'file_access',
            'target_id' => $path,
            'accessed_at' => now(),
            'metadata' => [
                'file_type' => $type,
            ],
        ]);

        return Storage::disk('iwosan_files')->response($path, null, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    private function filenameFromUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return null;
        }

        return basename($path);
    }

    private function isExpired(Certlice $certlice): bool
    {
        if (!$certlice->expires_at) {
            return false;
        }

        return \Carbon\Carbon::parse($certlice->expires_at)->isPast();
    }
}
