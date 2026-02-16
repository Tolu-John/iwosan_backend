<?php

namespace App\Jobs;

use App\Models\MetricDailySummary;
use App\Models\MetricEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ComputeDailyMetricsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public string $date;

    public function __construct(string $date)
    {
        $this->date = $date;
    }

    public function handle(): void
    {
        $day = Carbon::parse($this->date)->startOfDay();
        $next = $day->copy()->addDay();

        $events = MetricEvent::whereBetween('created_at', [$day, $next])->get();

        $byRole = $events->groupBy('actor_role');
        foreach ($byRole as $role => $group) {
            $counts = $this->countEvents($group);
            $summary = $this->buildSummary($counts);

            MetricDailySummary::updateOrCreate(
                ['date' => $day->toDateString(), 'actor_role' => $role ?: null, 'owner_type' => null, 'owner_id' => null],
                [
                    'conversion_rate' => $summary['conversion_rate'],
                    'completion_rate' => $summary['completion_rate'],
                    'refund_rate' => $summary['refund_rate'],
                    'counts' => $counts,
                ]
            );
        }

        $byOwner = $events->filter(function ($event) {
            return $event->owner_type && $event->owner_id;
        })->groupBy(function ($event) {
            return $event->owner_type.':'.$event->owner_id;
        });

        foreach ($byOwner as $key => $group) {
            [$ownerType, $ownerId] = explode(':', $key, 2);
            $counts = $this->countEvents($group);
            $summary = $this->buildSummary($counts);

            MetricDailySummary::updateOrCreate(
                [
                    'date' => $day->toDateString(),
                    'actor_role' => null,
                    'owner_type' => $ownerType,
                    'owner_id' => (int) $ownerId,
                ],
                [
                    'conversion_rate' => $summary['conversion_rate'],
                    'completion_rate' => $summary['completion_rate'],
                    'refund_rate' => $summary['refund_rate'],
                    'counts' => $counts,
                ]
            );
        }
    }

    private function countEvents($events): array
    {
        $counts = [
            'status_change' => 0,
            'appointment.scheduled' => 0,
            'appointment.completed' => 0,
            'appointment.cancelled' => 0,
            'appointment.no_show' => 0,
            'consultation.scheduled' => 0,
            'consultation.completed' => 0,
            'teletest.scheduled' => 0,
            'teletest.completed' => 0,
            'payment.paid' => 0,
            'payment.refunded' => 0,
        ];

        foreach ($events as $event) {
            if ($event->event_type === 'status_change') {
                $counts['status_change']++;
                $modelType = (string) $event->model_type;
                $to = $event->metadata['to'] ?? null;
                if ($modelType && $to) {
                    $key = $modelType.'.'.$to;
                    if (array_key_exists($key, $counts)) {
                        $counts[$key]++;
                    }
                }
            } else {
                if (array_key_exists($event->event_type, $counts)) {
                    $counts[$event->event_type]++;
                }
            }
        }

        return $counts;
    }

    private function buildSummary(array $counts): array
    {
        $scheduled = $counts['appointment.scheduled']
            + $counts['consultation.scheduled']
            + $counts['teletest.scheduled'];

        $completed = $counts['appointment.completed']
            + $counts['consultation.completed']
            + $counts['teletest.completed'];

        $cancelled = $counts['appointment.cancelled']
            + $counts['appointment.no_show'];

        $conversion_rate = $scheduled > 0 ? round($completed / $scheduled, 3) : null;
        $completion_rate = ($completed + $cancelled) > 0 ? round($completed / ($completed + $cancelled), 3) : null;
        $refund_rate = $counts['payment.paid'] > 0 ? round($counts['payment.refunded'] / $counts['payment.paid'], 3) : null;

        return [
            'conversion_rate' => $conversion_rate,
            'completion_rate' => $completion_rate,
            'refund_rate' => $refund_rate,
        ];
    }
}
