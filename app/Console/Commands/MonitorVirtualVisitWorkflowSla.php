<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\VirtualVisitWorkflowService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MonitorVirtualVisitWorkflowSla extends Command
{
    protected $signature = 'iwosan:monitor-virtual-visit-workflow-sla';
    protected $description = 'Enforce virtual visit workflow SLA transitions (payment expiry, waiting room opening).';

    public function handle(VirtualVisitWorkflowService $workflow): int
    {
        if (!(bool) config('virtual_visit_workflow.enabled', true)) {
            $this->info('Virtual visit workflow SLA monitor skipped because workflow flag is disabled.');
            return self::SUCCESS;
        }

        $now = Carbon::now();
        $expiredCount = 0;
        $waitingRoomOpenedCount = 0;
        $paymentWindowMinutes = (int) (config('virtual_visit_workflow.sla_windows.pending_payment.payment_window_minutes') ?? 30);
        $waitingRoomLeadMinutes = max(
            0,
            (int) (config('virtual_visit_workflow.sla_windows.waiting_room_open.open_lead_minutes') ?? 15)
        );
        $waitingRoomTimezone = trim((string) (config('virtual_visit_workflow.sla_windows.waiting_room_open.timezone') ?? 'Africa/Lagos'));
        if ($waitingRoomTimezone === '') {
            $waitingRoomTimezone = 'Africa/Lagos';
        }
        $waitingRoomNow = Carbon::now($waitingRoomTimezone);

        $pendingPayments = Appointment::query()
            ->where('appointment_type', 'like', '%virtual%')
            ->where('status', 'pending_payment')
            ->where('updated_at', '<=', $now->copy()->subMinutes($paymentWindowMinutes))
            ->get();

        foreach ($pendingPayments as $appointment) {
            try {
                $workflow->transitionSystem(
                    $appointment,
                    'payment_expired',
                    'payment_window_expired',
                    'Payment was not completed before the configured virtual visit payment window elapsed.'
                );
                $expiredCount++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $scheduledVirtualAppointments = Appointment::query()
            ->where('appointment_type', 'like', '%virtual%')
            ->where('status', 'scheduled')
            ->whereNotNull('date_time')
            ->get();

        foreach ($scheduledVirtualAppointments as $appointment) {
            $scheduledAt = $this->parseAppointmentDateTime($appointment->date_time, $waitingRoomTimezone);
            if (!$scheduledAt) {
                continue;
            }

            $windowOpensAt = $scheduledAt->copy()->subMinutes($waitingRoomLeadMinutes);
            if ($waitingRoomNow->lt($windowOpensAt)) {
                continue;
            }

            try {
                $workflow->transitionSystem(
                    $appointment,
                    'waiting_room_open',
                    'session_window_opened',
                    sprintf(
                        'Waiting room opened %d minute(s) before scheduled session time.',
                        $waitingRoomLeadMinutes
                    )
                );
                $waitingRoomOpenedCount++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $this->info(sprintf(
            'Virtual visit workflow SLA run complete. payment_expired=%d waiting_room_opened=%d',
            $expiredCount,
            $waitingRoomOpenedCount
        ));

        return self::SUCCESS;
    }

    private function parseAppointmentDateTime(?string $raw, string $timezone): ?Carbon
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value, $timezone);
        } catch (\Throwable) {
            return null;
        }
    }
}
