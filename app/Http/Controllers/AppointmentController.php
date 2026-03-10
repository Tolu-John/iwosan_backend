<?php

namespace App\Http\Controllers;

use App\Http\Requests\Appointments\StoreAppointmentRequest;
use App\Http\Requests\Appointments\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentCreatedResource;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Services\AccessService;
use App\Services\AppointmentService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AppointmentController extends Controller
{
    private AccessService $access;
    private AppointmentService $appointments;

    public function __construct(AccessService $access, AppointmentService $appointments)
    {
        $this->access = $access;
        $this->appointments = $appointments;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $scope = strtolower(trim((string) request()->query('status_scope', 'active')));
        if (!in_array($scope, ['active', 'past', 'all'], true)) {
            $scope = 'active';
        }

        $currentPatientId = $this->access->currentPatientId();
        if ($currentPatientId) {
            $appointment = Appointment::where('patient_id', $currentPatientId)->get();
            $this->appointments->enforceTimeouts($appointment);
            $appointment = $this->filterByScope($appointment, $scope);
            return response(AppointmentResource::collection($appointment), 200);
        }

        $currentCarerId = $this->access->currentCarerId();
        if ($currentCarerId) {
            $appointment = Appointment::where('carer_id', $currentCarerId)->get();
            $this->appointments->enforceTimeouts($appointment);
            $appointment = $this->filterByScope($appointment, $scope);
            return response(AppointmentResource::collection($appointment), 200);
        }

        $currentHospitalId = $this->access->currentHospitalId();
        if ($currentHospitalId) {
            $carerIds = \App\Models\Carer::where('hospital_id', $currentHospitalId)->pluck('id');
            $appointment = Appointment::whereIn('carer_id', $carerIds)->get();
            $this->appointments->enforceTimeouts($appointment);
            $appointment = $this->filterByScope($appointment, $scope);
            return response(AppointmentResource::collection($appointment), 200);
        }

        $appointment = collect();
        return response(AppointmentResource::collection($appointment)
        , 200);
    }

    private function filterByScope($appointments, string $scope)
    {
        if ($scope === 'all') {
            return $appointments->values();
        }

        $pastStatuses = ['completed', 'cancelled', 'no_show', 'rejected', 'failed', 'expired', 'sample_rejected'];

        if ($scope === 'past') {
            return $appointments
                ->filter(function ($item) use ($pastStatuses) {
                    $status = strtolower(trim((string) ($item->status ?? '')));
                    return in_array($status, $pastStatuses, true);
                })
                ->values();
        }

        return $appointments
            ->filter(function ($item) use ($pastStatuses) {
                $status = strtolower(trim((string) ($item->status ?? '')));
                return !in_array($status, $pastStatuses, true);
            })
            ->values();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreAppointmentRequest $request)
    {
        $data = $request->validated();
        $data['consent_accepted'] = (bool) $request->boolean('consent_accepted');
        $data['address_lat'] = $request->input('address_lat');
        $data['address_lon'] = $request->input('address_lon');
        $data['attachments_json'] = $this->storeAttachments($request);
        $appointment = $this->appointments->create($data, $this->access);

        return response(new AppointmentCreatedResource($appointment), 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $appointment=Appointment::find( $id);
        if (is_null($appointment)) {
            return $this->sendError('Appointment not found.');
            }

        $this->authorize('view', $appointment);
        $appointment = $this->appointments->enforceTimeoutFor($appointment);
        
            return response( new AppointmentResource($appointment)
            , 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateAppointmentRequest $request, $id)
    {
        $appointment = Appointment::find($id);
        if (!$appointment) {
            return $this->sendError('Appointment not found.');
        }

        $this->authorize('update', $appointment);

        $data = $request->validated();
        if ($request->has('consent_accepted')) {
            $data['consent_accepted'] = (bool) $request->boolean('consent_accepted');
        }
        $data['address_lat'] = $request->input('address_lat');
        $data['address_lon'] = $request->input('address_lon');
        $attachments = $this->storeAttachments($request);
        if (!empty($attachments)) {
            $data['attachments_json'] = $attachments;
        }
        $appointment = $this->appointments->update($appointment, $data, $this->access);

        return response(new AppointmentResource($appointment), 200);
    
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $appointment=Appointment::find( $id);
        if (!$appointment) {
            return $this->sendError('Appointment not found.');
        }

        $this->authorize('delete', $appointment);
        $appointment->delete();
   
        return response(['message' => 'Deleted']);
    }

    private function storeAttachments(Request $request): array
    {
        $files = $request->file('attachments', []);
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }
        $urls = [];
        if (is_array($files)) {
            foreach ($files as $file) {
                if (!$file) {
                    continue;
                }
                $path = $file->store('appointment', 'iwosan_files');
                $path = str_replace('\\', '/', $path);
                $urls[] = url('/api/storage/' . $path);
            }
        }

        $payloads = $request->input('attachments_payload', []);
        if (is_array($payloads)) {
            foreach ($payloads as $payload) {
                if (!is_array($payload)) {
                    continue;
                }
                $base64 = trim((string) ($payload['file_base64'] ?? ''));
                if ($base64 === '') {
                    continue;
                }
                $originalName = trim((string) ($payload['file_name'] ?? ''));
                $urls[] = $this->storeBase64Attachment($base64, $originalName);
            }
        }

        return $urls;
    }

    private function storeBase64Attachment(string $base64, string $originalName = ''): string
    {
        $raw = $base64;
        $mime = null;
        if (preg_match('/^data:(.*?);base64,/', $raw, $matches)) {
            $mime = $matches[1] ?? null;
            $raw = substr($raw, strpos($raw, ',') + 1);
        }

        $decoded = base64_decode($raw, true);
        if ($decoded === false || $decoded === '') {
            abort(422, 'Invalid attachment payload.');
        }

        $maxBytes = 20 * 1024 * 1024;
        if (strlen($decoded) > $maxBytes) {
            abort(422, 'Attachment exceeds maximum size of 20MB.');
        }

        $ext = $this->guessAttachmentExtension($mime, $originalName);
        $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'heic', 'heif', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            abort(422, 'Attachment format is not supported.');
        }

        $base = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'appointment-file';
        $filename = sprintf('%s-%s.%s', $base, Str::random(8), $ext);
        $location = 'appointment/'.$filename;
        Storage::disk('iwosan_files')->put($location, $decoded);

        return url('/api/storage/appointment/'.$filename);
    }

    private function guessAttachmentExtension(?string $mime, string $originalName): string
    {
        $fileExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($fileExt !== '') {
            return $fileExt;
        }

        return match (strtolower((string) $mime)) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }
}
