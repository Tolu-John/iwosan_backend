<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewCertliceRequest;
use App\Http\Requests\StoreCertliceRequest;
use App\Http\Requests\UpdateCertliceRequest;
use App\Http\Resources\CertlicePublicResource;
use App\Http\Resources\CertliceStaffResource;
use App\Models\Carer;
use App\Models\Certlice;
use App\Models\CertliceAuditLog;
use App\Models\Hospital;
use App\Models\User;
use App\Services\AccessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CertliceController extends Controller
{
    private AccessService $access;

    public function __construct(AccessService $access)
    {
        $this->access = $access;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $currentCarerId = $this->access->currentCarerId();
        if ($currentCarerId) {
            $certlice = Certlice::where('type', 'carer')->where('type_id', $currentCarerId)->get();
            return response(CertliceStaffResource::collection($certlice), 200);
        }

        $currentHospitalId = $this->access->currentHospitalId();
        if ($currentHospitalId) {
            $certlice = Certlice::where('type', 'carer')
                ->orWhere(function ($query) use ($currentHospitalId) {
                    $query->where('type', 'hospital')->where('type_id', $currentHospitalId);
                })
                ->get();
            return response(CertliceStaffResource::collection($certlice), 200);
        }

        $currentPatientId = $this->access->currentPatientId();
        if ($currentPatientId) {
            $includeHistory = (bool) $request->query('include_history');
            $certlice = Certlice::where('status', 'verified')
                ->when(!$includeHistory, function ($query) {
                    $query->where(function ($inner) {
                        $inner->whereNull('expires_at')
                            ->orWhere('expires_at', '>=', Carbon::now());
                    });
                })
                ->get();
            return response(CertlicePublicResource::collection($certlice), 200);
        }

        $certlice = collect();

        return response(CertliceStaffResource::collection($certlice), 200);
    }

    public function create()
    {
        //
    }

    public function store(StoreCertliceRequest $request)
    {
        $data = $request->validated();

        $type = strtolower(trim($data['type'], "\"\'"));
        if ($type === 'carer') {
            $currentCarerId = $this->access->currentCarerId();
            $allowed = $currentCarerId && (int) $data['type_id'] === (int) $currentCarerId;
        } elseif ($type === 'hospital') {
            $currentHospitalId = $this->access->currentHospitalId();
            $allowed = $currentHospitalId && (int) $data['type_id'] === (int) $currentHospitalId;
        } else {
            return response(['message' => 'Invalid certlice type.'], 422);
        }

        $deny = $this->access->denyIfFalse($allowed);
        if ($deny) {
            return $deny;
        }

        if (!$request->file('file')->isValid()) {
            return response('Invalid File', 422);
        }

        $newFileName = trim(str_replace(' ', '', $data['file_name']), "\"\'");
        $file = $request->file('file');
        $extension = $file->extension();

        $url = null;
        if ($type === 'hospital') {
            $names = Hospital::select(['name'])->where('id', $data['type_id'])->first();
            $named = str_replace(' ', '', $names['name']) . $newFileName;
            $rPath = $named . '.' . $extension;
            $request->file('file')->storeAs('certlices/hospital/', $rPath, 'iwosan_files');
            $url = url('/') . '/api/storage/certlices/hospital/' . $rPath;
        } else {
            $userId = Carer::select(['user_id'])->where('id', $data['type_id'])->first();
            $names = User::select(['firstname', 'lastname'])->where('id', $userId->user_id)->first();
            $named = $names['firstname'] . $names['lastname'] . $newFileName;
            $rPath = $named . '.' . $extension;
            $request->file('file')->storeAs('certlices/carer/', $rPath, 'iwosan_files');
            $url = url('/') . '/api/storage/certlices/carer/' . $rPath;
        }

        if (!$url) {
            return response('File Upload Error', 422);
        }

        $certlice = new Certlice();
        $certlice->file_name = trim($data['file_name'], "\"\'");
        $certlice->location = $url;
        $certlice->type_id = $data['type_id'];
        $certlice->type = trim($data['type'], "\"\'");
        $certlice->cert_type = $data['cert_type'];
        $certlice->issuer = $data['issuer'];
        $certlice->license_number = $data['license_number'];
        $certlice->status = 'pending';
        $certlice->issued_at = $data['issued_at'];
        $certlice->expires_at = $data['expires_at'] ?? null;
        $certlice->verified_at = null;
        $certlice->verified_by = null;
        $certlice->notes = $data['notes'] ?? null;
        $certlice->save();

        $this->logAudit($certlice, 'created', null, 'pending', null);

        return response(new CertliceStaffResource($certlice), 200);
    }

    public function show(Certlice $certlice)
    {
        if (is_null($certlice)) {
            return $this->sendError('File not found.');
        }

        $deny = $this->access->denyIfFalse($this->access->canAccessCertlice($certlice));
        if ($deny) {
            return $deny;
        }

        if ($this->access->currentPatientId()) {
            if ($certlice->status !== 'verified' || $this->isExpired($certlice)) {
                return response()->json(['message' => 'Not found'], 404);
            }
            return response(new CertlicePublicResource($certlice), 200);
        }

        return response(new CertliceStaffResource($certlice), 200);
    }

    public function edit(Certlice $certlice)
    {
        //
    }

    public function update(UpdateCertliceRequest $request, $id)
    {
        $data = $request->validated();

        $certlice = Certlice::find($id);
        if (is_null($certlice)) {
            return $this->sendError('File not found.');
        }

        $deny = $this->access->denyIfFalse($this->access->canAccessCertlice($certlice, true));
        if ($deny) {
            return $deny;
        }

        if (!$this->canOwnerUpdate($certlice)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!is_null($request->file('file'))) {
            if ($request->file('file')->isValid()) {
                $newFileName = trim(str_replace(' ', '', $data['file_name']), "\"\'");
                $file = $request->file('file');
                $extension = $file->extension();

                $url = null;
                $type = trim($data['type'], "\"\'");

                $util = new DashboardControllerA;
                $locUrl = '';

                if ($type === 'hospital') {
                    $names = Hospital::select(['name'])->where('id', $data['type_id'])->first();
                    $named = str_replace(' ', '', $names['name']) . $newFileName;

                    $rPath = $named . '.' . $extension;

                    $request->file('file')->storeAs('certlices/hospital/', $rPath, 'iwosan_files');

                    $url = url('/') . '/api/storage/certlices/hospital/' . $rPath;

                    $locUrl = 'certlices/hospital/';
                } else {
                    $userId = Carer::select(['user_id'])->where('id', $data['type_id'])->first();
                    $names = User::select(['firstname', 'lastname'])->where('id', $userId->user_id)->first();
                    $named = $names['firstname'] . $names['lastname'] . $newFileName;

                    $rPath = $named . '.' . $extension;

                    $request->file('file')->storeAs('certlices/carer/', $rPath, 'iwosan_files');

                    $url = url('/') . '/api/storage/certlices/carer/' . $rPath;

                    $locUrl = 'certlices/carer/';
                }

                if ($url) {
                    $util->deletefile($locUrl, $certlice->location, $rPath);
                    $certlice->location = $url;
                } else {
                    return response('File Upload Error', 422);
                }
            } else {
                return response('Invalid File', 422);
            }
        }

        $fromStatus = $certlice->status;
        $certlice->file_name = trim($data['file_name'], "\"\'");
        $certlice->type_id = $data['type_id'];
        $certlice->type = trim($data['type'], "\"\'");
        $certlice->cert_type = $data['cert_type'];
        $certlice->issuer = $data['issuer'];
        $certlice->license_number = $data['license_number'];
        $certlice->issued_at = $data['issued_at'];
        $certlice->expires_at = $data['expires_at'] ?? null;
        $certlice->notes = $data['notes'] ?? null;
        $certlice->status = 'pending';
        $certlice->verified_at = null;
        $certlice->verified_by = null;
        $certlice->save();

        $this->logAudit($certlice, 'updated', $fromStatus, 'pending', null);

        return response()->json(new CertliceStaffResource($certlice), 200);
    }

    public function destroy(Certlice $certlice)
    {
        $deny = $this->access->denyIfFalse($this->access->canAccessCertlice($certlice, true));
        if ($deny) {
            return $deny;
        }

        $certlice->delete();

        $util = new DashboardControllerA;

        if ($certlice->type == 'carer') {
            $util->deletefile('certlices/carer/', $certlice->location, ' ');
        } else if ($certlice->type == 'hospital') {
            $util->deletefile('certlices/hospital/', $certlice->location, ' ');
        }

        $this->logAudit($certlice, 'deleted', $certlice->status, null, null);

        return response(['message' => 'Deleted']);
    }

    public function review(ReviewCertliceRequest $request, $id)
    {
        $certlice = Certlice::find($id);
        if (!$certlice) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if (!$this->canReviewerUpdate($certlice)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($request->status === 'verified' && $this->isExpired($certlice)) {
            return response()->json(['message' => 'Cannot verify an expired certificate'], 422);
        }

        $fromStatus = $certlice->status;
        $certlice->status = $request->status;
        $certlice->verified_at = $request->status === 'verified' ? Carbon::now() : null;
        $certlice->verified_by = $request->status === 'verified' ? Auth::id() : null;
        if ($request->reason) {
            $certlice->notes = $request->reason;
        }
        $certlice->save();

        $this->logAudit($certlice, 'reviewed', $fromStatus, $certlice->status, $request->reason);

        return response(new CertliceStaffResource($certlice), 200);
    }

    private function canReviewerUpdate(Certlice $certlice): bool
    {
        $type = strtolower($certlice->type);

        if ($type === 'hospital') {
            $currentHospitalId = $this->access->currentHospitalId();
            return $currentHospitalId && (int) $certlice->type_id === (int) $currentHospitalId;
        }

        if ($type === 'carer') {
            $currentHospitalId = $this->access->currentHospitalId();
            if (!$currentHospitalId) {
                return false;
            }

            $carer = Carer::find($certlice->type_id);
            return $carer && (int) $carer->hospital_id === (int) $currentHospitalId;
        }

        return false;
    }

    private function canOwnerUpdate(Certlice $certlice): bool
    {
        $type = strtolower($certlice->type);

        if ($type === 'carer') {
            $currentCarerId = $this->access->currentCarerId();
            return $currentCarerId && (int) $certlice->type_id === (int) $currentCarerId;
        }

        if ($type === 'hospital') {
            $currentHospitalId = $this->access->currentHospitalId();
            return $currentHospitalId && (int) $certlice->type_id === (int) $currentHospitalId;
        }

        return false;
    }

    private function isExpired(Certlice $certlice): bool
    {
        if (!$certlice->expires_at) {
            return false;
        }

        return Carbon::parse($certlice->expires_at)->isPast();
    }

    private function logAudit(Certlice $certlice, string $action, ?string $fromStatus, ?string $toStatus, ?string $reason): void
    {
        CertliceAuditLog::create([
            'certlice_id' => $certlice->id,
            'actor_id' => Auth::id(),
            'actor_role' => $this->currentActorRole(),
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
        ]);
    }

    private function currentActorRole(): string
    {
        if ($this->access->currentCarerId()) {
            return 'carer';
        }
        if ($this->access->currentHospitalId()) {
            return 'hospital';
        }
        if ($this->access->currentPatientId()) {
            return 'patient';
        }

        return 'system';
    }
}
