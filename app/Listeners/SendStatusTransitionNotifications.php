<?php

namespace App\Listeners;

use App\Events\StatusTransitioned;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Hospital;
use App\Models\Teletest;
use App\Services\EmailService;
use App\Services\PushService;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendStatusTransitionNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;
    public int $backoff = 30;

    private EmailService $email;
    private SmsService $sms;
    private PushService $push;

    public function __construct(EmailService $email, SmsService $sms, PushService $push)
    {
        $this->email = $email;
        $this->sms = $sms;
        $this->push = $push;
    }

    public function handle(StatusTransitioned $event): void
    {
        $payload = $this->resolvePayload($event->modelType, $event->modelId);
        if (!$payload) {
            return;
        }

        $status = $event->toStatus;
        $fromStatus = $event->fromStatus;
        $reason = $event->context['reason'] ?? null;

        $subject = sprintf('Status update: %s #%d', ucfirst($event->modelType), $event->modelId);
        $message = $this->buildMessage($event->modelType, $event->modelId, $fromStatus, $status, $reason);

        foreach ($payload['recipients'] as $recipient) {
            if (!$this->shouldNotifyRole($recipient['role'], $status)) {
                continue;
            }

            if (!empty($recipient['email'])) {
                $this->email->send($recipient['email'], $subject, $message);
            }

            if (!empty($recipient['phone'])) {
                $this->sms->send($recipient['phone'], $message);
            }

            $this->push->send($recipient['device_token'] ?? null, $subject, $message, [
                'model_type' => $event->modelType,
                'model_id' => $event->modelId,
                'from_status' => $fromStatus,
                'to_status' => $status,
            ]);
        }
    }

    private function resolvePayload(string $modelType, int $modelId): ?array
    {
        if ($modelType === 'appointment') {
            $appointment = Appointment::find($modelId);
            if (!$appointment) {
                return null;
            }

            $patientUser = $appointment->patient?->user;
            $carerUser = $appointment->carer?->user;
            $hospital = $appointment->carer?->hospital;

            return [
                'recipients' => [
                    [
                        'role' => 'patient',
                        'email' => $patientUser?->email,
                        'phone' => $patientUser?->phone,
                        'device_token' => null,
                    ],
                    [
                        'role' => 'carer',
                        'email' => $carerUser?->email,
                        'phone' => $carerUser?->phone,
                        'device_token' => null,
                    ],
                    [
                        'role' => 'hospital',
                        'email' => $hospital?->email,
                        'phone' => $hospital?->phone,
                        'device_token' => null,
                    ],
                ],
            ];
        }

        if ($modelType === 'consultation') {
            $consultation = Consultation::find($modelId);
            if (!$consultation) {
                return null;
            }

            $patientUser = $consultation->patient?->user;
            $carerUser = $consultation->carer?->user;
            $hospital = $consultation->hospital_id ? Hospital::find($consultation->hospital_id) : null;

            return [
                'recipients' => [
                    [
                        'role' => 'patient',
                        'email' => $patientUser?->email,
                        'phone' => $patientUser?->phone,
                        'device_token' => null,
                    ],
                    [
                        'role' => 'carer',
                        'email' => $carerUser?->email,
                        'phone' => $carerUser?->phone,
                        'device_token' => null,
                    ],
                    [
                        'role' => 'hospital',
                        'email' => $hospital?->email,
                        'phone' => $hospital?->phone,
                        'device_token' => null,
                    ],
                ],
            ];
        }

        if ($modelType === 'teletest') {
            $teletest = Teletest::find($modelId);
            if (!$teletest) {
                return null;
            }

            $patientUser = $teletest->patient?->user;
            $carerUser = $teletest->carer?->user;
            $hospital = $teletest->hospital_id ? $teletest->hospital : null;

            return [
                'recipients' => [
                    [
                        'role' => 'patient',
                        'email' => $patientUser?->email,
                        'phone' => $patientUser?->phone,
                        'device_token' => null,
                    ],
                    [
                        'role' => 'carer',
                        'email' => $carerUser?->email,
                        'phone' => $carerUser?->phone,
                        'device_token' => null,
                    ],
                    [
                        'role' => 'hospital',
                        'email' => $hospital?->email,
                        'phone' => $hospital?->phone,
                        'device_token' => null,
                    ],
                ],
            ];
        }

        return null;
    }

    private function shouldNotifyRole(string $role, string $toStatus): bool
    {
        if ($role === 'patient') {
            return true;
        }

        if (in_array($role, ['carer', 'hospital'], true)) {
            return in_array($toStatus, ['scheduled', 'in_progress', 'completed', 'cancelled', 'no_show'], true);
        }

        return false;
    }

    private function buildMessage(string $modelType, int $modelId, ?string $fromStatus, string $toStatus, ?string $reason): string
    {
        $message = sprintf(
            '%s #%d status changed from %s to %s.',
            ucfirst($modelType),
            $modelId,
            $fromStatus ?? 'unknown',
            $toStatus
        );

        if ($reason) {
            $message .= ' Reason: ' . $reason;
        }

        return $message;
    }
}
