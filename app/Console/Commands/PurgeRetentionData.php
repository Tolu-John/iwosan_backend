<?php

namespace App\Console\Commands;

use App\Models\CertliceAuditLog;
use App\Models\CommEvent;
use App\Models\ComplaintAuditLog;
use App\Models\LabResultAuditLog;
use App\Models\PaymentAuditLog;
use App\Models\PhiAccessLog;
use App\Models\ReviewAuditLog;
use App\Models\SecurityIncident;
use App\Models\VitalAuditLog;
use App\Models\WardAuditLog;
use App\Models\WardVitalAuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class PurgeRetentionData extends Command
{
    protected $signature = 'compliance:purge-retention';
    protected $description = 'Purge logs and temporary files based on retention policy';

    public function handle(): int
    {
        $forceDelete = (bool) config('retention.force_delete');

        $this->purgeModel(PhiAccessLog::class, 'retention.phi_access_logs_days', $forceDelete);
        $this->purgeModel(SecurityIncident::class, 'retention.security_incidents_days', $forceDelete);
        $this->purgeModel(CommEvent::class, 'retention.comm_events_days', $forceDelete);
        $this->purgeModel(VitalAuditLog::class, 'retention.vital_audit_logs_days', $forceDelete);
        $this->purgeModel(WardVitalAuditLog::class, 'retention.ward_vital_audit_logs_days', $forceDelete);
        $this->purgeModel(WardAuditLog::class, 'retention.ward_audit_logs_days', $forceDelete);
        $this->purgeModel(LabResultAuditLog::class, 'retention.lab_result_audit_logs_days', $forceDelete);
        $this->purgeModel(ReviewAuditLog::class, 'retention.review_audit_logs_days', $forceDelete);
        $this->purgeModel(ComplaintAuditLog::class, 'retention.complaint_audit_logs_days', $forceDelete);
        $this->purgeModel(PaymentAuditLog::class, 'retention.payment_audit_logs_days', $forceDelete);
        $this->purgeModel(CertliceAuditLog::class, 'retention.certlice_audit_logs_days', $forceDelete);

        $this->purgeTempFiles((int) config('retention.temp_files_days', 30));

        $this->info('Retention purge completed.');
        return 0;
    }

    private function purgeModel(string $modelClass, string $configKey, bool $forceDelete): void
    {
        $days = (int) config($configKey, 365);
        $cutoff = Carbon::now()->subDays($days);

        $query = $modelClass::where('created_at', '<', $cutoff);
        if ($forceDelete && method_exists($modelClass, 'withTrashed')) {
            $query = $modelClass::withTrashed()->where('created_at', '<', $cutoff);
            $query->forceDelete();
            return;
        }

        $query->delete();
    }

    private function purgeTempFiles(int $days): void
    {
        $cutoff = Carbon::now()->subDays($days)->getTimestamp();
        $paths = [
            storage_path('app/tmp'),
            storage_path('app/temp'),
            storage_path('app/exports'),
        ];

        foreach ($paths as $path) {
            if (!File::exists($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                if ($file->getMTime() < $cutoff) {
                    @unlink($file->getPathname());
                }
            }
        }
    }
}
