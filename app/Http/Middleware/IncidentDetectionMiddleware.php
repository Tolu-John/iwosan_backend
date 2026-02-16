<?php

namespace App\Http\Middleware;

use App\Models\SecurityIncident;
use App\Services\NotificationFallbackService;
use App\Services\SmsFallbackService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class IncidentDetectionMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $user = Auth::user();
        $key = 'incident:rate:' . ($user?->id ?? $request->ip());
        $count = Cache::increment($key);
        Cache::put($key, $count, now()->addMinute());

        if ($count > 200) {
            $incident = SecurityIncident::create([
                'user_id' => $user?->id,
                'type' => 'rate_anomaly',
                'severity' => 'medium',
                'route' => $request->path(),
                'ip' => $request->ip(),
                'metadata' => ['count' => $count],
                'detected_at' => now(),
            ]);

            $this->notify($incident);
        }

        return $response;
    }

    private function notify(SecurityIncident $incident): void
    {
        $emails = config('compliance.incident_notify_emails', []);
        $smsNumbers = config('compliance.incident_notify_sms', []);

        if (empty($emails) && empty($smsNumbers)) {
            return;
        }

        $subject = 'Security incident detected';
        $mailer = app(NotificationFallbackService::class);

        foreach ($emails as $email) {
            $mailer->sendEmailTemplate($email, $subject, 'emails.security-incident', [
                'type' => $incident->type,
                'severity' => $incident->severity,
                'route' => $incident->route,
                'ip' => $incident->ip,
            ]);
        }

        if (!empty($smsNumbers)) {
            $smsBody = view('sms.security-incident', ['incident' => $incident])->render();
            $sms = app(SmsFallbackService::class);
            foreach ($smsNumbers as $number) {
                try {
                    $sms->sendSms($number, $smsBody);
                } catch (\Throwable $e) {
                    // Ignore SMS failures to avoid blocking request flow
                }
            }
        }
    }
}
