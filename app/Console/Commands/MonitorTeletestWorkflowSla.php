<?php

namespace App\Console\Commands;

use App\Models\Teletest;
use App\Services\TeletestWorkflowService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MonitorTeletestWorkflowSla extends Command
{
    protected $signature = 'iwosan:monitor-teletest-workflow-sla';
    protected $description = 'Enforce teletest workflow SLA transitions (assignment timeout, payment expiry, ETA breach, lab delay).';

    public function handle(TeletestWorkflowService $workflow): int
    {
        if (!(bool) config('teletest_workflow.enabled', true)) {
            $this->info('Teletest workflow SLA monitor skipped because workflow flag is disabled.');
            return self::SUCCESS;
        }

        $now = Carbon::now();
        $counters = [
            'technician_timeout' => 0,
            'payment_expired' => 0,
            'arrival_breach' => 0,
            'lab_delay_escalation' => 0,
        ];

        $techTimeoutMinutes = (int) (config('teletest_workflow.sla_windows.awaiting_technician_approval.technician_response_minutes') ?? 15);
        $paymentWindowMinutes = (int) (config('teletest_workflow.sla_windows.awaiting_payment.payment_window_minutes') ?? 30);
        $latenessThresholdMinutes = (int) (config('teletest_workflow.sla_windows.en_route.lateness_threshold_minutes') ?? 30);
        $labDelayMinutes = (int) env('TELETEST_LAB_PROCESSING_DELAY_MINUTES', 1440);

        $technicianPending = Teletest::where('status', 'awaiting_technician_approval')
            ->where('updated_at', '<=', $now->copy()->subMinutes($techTimeoutMinutes))
            ->get();

        foreach ($technicianPending as $teletest) {
            try {
                $workflow->transitionSystem(
                    $teletest,
                    'technician_reassignment_pending',
                    'technician_response_timeout',
                    'Assigned technician did not respond within SLA window.'
                );
                $counters['technician_timeout']++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $awaitingPayment = Teletest::where('status', 'awaiting_payment')
            ->where('updated_at', '<=', $now->copy()->subMinutes($paymentWindowMinutes))
            ->get();

        foreach ($awaitingPayment as $teletest) {
            try {
                $workflow->transitionSystem(
                    $teletest,
                    'payment_expired',
                    'payment_window_expired',
                    'Payment was not completed before the configured window elapsed.'
                );
                $counters['payment_expired']++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $enRoute = Teletest::where('status', 'en_route')->get();
        foreach ($enRoute as $teletest) {
            $scheduled = $teletest->date_time ? Carbon::parse((string) $teletest->date_time) : null;
            if (!$scheduled) {
                continue;
            }

            if ($now->greaterThan($scheduled->copy()->addMinutes($latenessThresholdMinutes))) {
                try {
                    $workflow->transitionSystem(
                        $teletest,
                        'arrival_breach',
                        'eta_breach',
                        'Technician arrival breached lateness threshold.'
                    );
                    $counters['arrival_breach']++;
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        $labProcessing = Teletest::where('status', 'lab_processing')
            ->where('updated_at', '<=', $now->copy()->subMinutes($labDelayMinutes))
            ->get();

        foreach ($labProcessing as $teletest) {
            try {
                $workflow->transitionSystem(
                    $teletest,
                    'escalation_open',
                    'lab_processing_delay',
                    'Lab processing exceeded expected turnaround threshold.'
                );
                $counters['lab_delay_escalation']++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $this->info(sprintf(
            'Teletest workflow SLA run complete. technician_timeout=%d payment_expired=%d arrival_breach=%d lab_delay_escalation=%d',
            $counters['technician_timeout'],
            $counters['payment_expired'],
            $counters['arrival_breach'],
            $counters['lab_delay_escalation']
        ));

        return self::SUCCESS;
    }
}
