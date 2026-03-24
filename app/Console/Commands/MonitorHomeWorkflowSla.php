<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonitorHomeWorkflowSla extends Command
{
    protected $signature = 'iwosan:monitor-home-workflow-sla';
    protected $description = 'Enforce home workflow SLA transitions (expiry, due, overdue).';

    public function handle(AppointmentService $appointments): int
    {
        $now = Carbon::now();
        $expired = 0;
        $due = 0;
        $overdue = 0;

        $pendingAdmissions = Appointment::where('appointment_type', 'like', '%home%')
            ->where('status', 'home_admitted_pending_payment')
            ->get();

        foreach ($pendingAdmissions as $appointment) {
            $quote = DB::table('home_admission_quotes')
                ->where('appointment_id', $appointment->id)
                ->orderByDesc('version')
                ->first();

            if (!$quote || !$quote->valid_until) {
                continue;
            }

            if (Carbon::parse((string) $quote->valid_until)->lessThanOrEqualTo($now)) {
                $appointments->transitionSystem(
                    $appointment,
                    'admission_expired',
                    'quote_expired',
                    'Admission quote expired before payment.',
                    ['quote_id' => $quote->id]
                );
                $expired++;
            }
        }

        $activeAppointments = Appointment::where('appointment_type', 'like', '%home%')
            ->where('status', 'home_admitted_active')
            ->get();

        foreach ($activeAppointments as $appointment) {
            $episode = DB::table('home_care_episodes')
                ->where('appointment_id', $appointment->id)
                ->orderByDesc('id')
                ->first();
            if (!$episode) {
                continue;
            }

            $cycle = DB::table('episode_billing_cycles')
                ->where('episode_id', $episode->id)
                ->where('billing_status', 'due')
                ->orderBy('due_at')
                ->first();

            if (!$cycle || !$cycle->due_at) {
                continue;
            }

            if (Carbon::parse((string) $cycle->due_at)->lessThanOrEqualTo($now)) {
                $appointments->transitionSystem(
                    $appointment,
                    'payment_due',
                    'billing_cycle_due',
                    'Recurring billing cycle reached due date.',
                    ['billing_cycle_id' => $cycle->id]
                );
                $due++;
            }
        }

        $dueAppointments = Appointment::where('appointment_type', 'like', '%home%')
            ->where('status', 'payment_due')
            ->get();

        foreach ($dueAppointments as $appointment) {
            $episode = DB::table('home_care_episodes')
                ->where('appointment_id', $appointment->id)
                ->orderByDesc('id')
                ->first();
            if (!$episode) {
                continue;
            }

            $cycle = DB::table('episode_billing_cycles')
                ->where('episode_id', $episode->id)
                ->whereIn('billing_status', ['due', 'overdue'])
                ->orderBy('due_at')
                ->first();

            if (!$cycle) {
                continue;
            }

            $graceUntil = $cycle->grace_until ? Carbon::parse((string) $cycle->grace_until) : Carbon::parse((string) $cycle->due_at)->addDays(7);
            if ($graceUntil->lessThanOrEqualTo($now)) {
                DB::table('episode_billing_cycles')->where('id', $cycle->id)->update([
                    'billing_status' => 'overdue',
                    'updated_at' => Carbon::now(),
                ]);

                $appointments->transitionSystem(
                    $appointment,
                    'payment_overdue',
                    'billing_cycle_overdue',
                    'Billing cycle exceeded grace period.',
                    ['billing_cycle_id' => $cycle->id]
                );
                $overdue++;
            }
        }

        $this->info("Home workflow SLA run complete. expired={$expired}, due={$due}, overdue={$overdue}");

        return self::SUCCESS;
    }
}
