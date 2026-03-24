<?php

namespace App\Http\Controllers;

use App\Http\Requests\Appointments\StoreAppointmentRequest;
use App\Http\Requests\Appointments\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentCreatedResource;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Services\AccessService;
use App\Services\AppointmentService;
use App\Services\VirtualVisitWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AppointmentController extends Controller
{
    private AccessService $access;
    private AppointmentService $appointments;
    private VirtualVisitWorkflowService $virtualWorkflow;

    public function __construct(
        AccessService $access,
        AppointmentService $appointments,
        VirtualVisitWorkflowService $virtualWorkflow
    )
    {
        $this->access = $access;
        $this->appointments = $appointments;
        $this->virtualWorkflow = $virtualWorkflow;
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

        $pastStatuses = [
            'completed',
            'visit_completed',
            'episode_completed',
            'episode_closed_nonpayment',
            'admission_rejected',
            'admission_cancelled',
            'cancelled',
            'no_show',
            'rejected',
            'failed',
            'expired',
            'sample_rejected',
        ];

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

    public function action(Request $request, $id, string $actionKey)
    {
        $appointment = Appointment::find($id);
        if (!$appointment) {
            return $this->sendError('Appointment not found.');
        }

        $this->authorize('update', $appointment);

        $actionKey = strtolower(trim($actionKey));

        $data = $request->validate([
            'status_reason' => 'nullable|string|max:255',
            'status_reason_code' => 'nullable|string|max:120',
            'status_reason_note' => 'nullable|string|max:2000',
            'disposition' => 'nullable|string|max:120',
            'pathway' => 'nullable|string|max:60',
            'severity' => 'nullable|string|max:40',
            'payment_id' => 'nullable|integer',
            'current_eta_minutes' => 'nullable|integer|min:0|max:720',
            'assignment_source' => 'nullable|string|max:80',
            'currency' => 'nullable|string|max:8',
            'billing_cycle' => 'nullable|string|max:40',
            'quote_valid_until' => 'nullable|date',
            'enrollment_fee_minor' => 'nullable|integer|min:0',
            'recurring_fee_minor' => 'nullable|integer|min:0',
            'addons_total_minor' => 'nullable|integer|min:0',
            'discount_total_minor' => 'nullable|integer|min:0',
            'tax_total_minor' => 'nullable|integer|min:0',
            'grand_total_minor' => 'nullable|integer|min:0',
        ]);

        if ($actionKey === 'approve_quote') {
            $requiredQuoteFields = [
                'enrollment_fee_minor',
                'recurring_fee_minor',
                'billing_cycle',
                'addons_total_minor',
                'tax_total_minor',
                'discount_total_minor',
            ];
            foreach ($requiredQuoteFields as $field) {
                if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                    abort(422, "{$field} is required for {$actionKey}.");
                }
            }
            if (
                $actionKey === 'approve_quote' &&
                (int) $data['enrollment_fee_minor'] === 0 &&
                (int) $data['recurring_fee_minor'] === 0
            ) {
                abort(422, 'Quote must include enrollment or recurring fee.');
            }
        }

        if (in_array($actionKey, [
            'request_quote_revision',
            'reject',
            'reject_admission',
            'request_changes',
            'cancel_admission',
            'pause_care_non_critical',
            'close_episode_nonpayment',
            'mark_escalation_unresolved',
        ], true)) {
            $note = trim((string) ($data['status_reason_note'] ?? ''));
            if ($note === '') {
                abort(422, "status_reason_note is required for {$actionKey}.");
            }
            if (trim((string) ($data['status_reason'] ?? '')) === '') {
                $data['status_reason'] = $note;
            }
        }

        $appointment = $this->appointments->transitionByAction($appointment, $actionKey, $data, $this->access);

        return response(new AppointmentResource($appointment), 200);
    }

    public function virtualAction(Request $request, $id, string $actionKey)
    {
        if (!(bool) config('virtual_visit_workflow.enabled', true)) {
            return response()->json([
                'message' => 'Virtual visit workflow actions are currently disabled.',
            ], 409);
        }

        $appointment = Appointment::find($id);
        if (!$appointment) {
            return $this->sendError('Appointment not found.');
        }

        $this->authorize('update', $appointment);

        $actionKey = strtolower(trim($actionKey));

        $data = $request->validate([
            'target_status' => 'nullable|string|max:120',
            'status_reason' => 'nullable|string|max:255',
            'status_reason_code' => 'nullable|string|max:120',
            'status_reason_note' => 'nullable|string|max:2000',
            'payment_id' => 'nullable|integer',
            'carer_id' => 'nullable|integer',
            'reassigned_to' => 'nullable|integer',
            'current_eta_minutes' => 'nullable|integer|min:0|max:720',
            'severity' => 'nullable|string|max:40',
            'pathway' => 'nullable|string|max:60',
            'consent_version' => 'nullable|string|max:40',
            'consent_text_hash' => 'nullable|string|max:128',
            'consent_accepted' => 'nullable|boolean',
            'closeout_submitted' => 'nullable|boolean',
        ]);

        if (!array_key_exists('carer_id', $data) && array_key_exists('reassigned_to', $data)) {
            $data['carer_id'] = $data['reassigned_to'];
        }

        $appointment = $this->virtualWorkflow->runAction(
            $appointment,
            $actionKey,
            $data,
            $this->access
        );

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
