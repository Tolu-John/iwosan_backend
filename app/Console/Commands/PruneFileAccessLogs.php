<?php

namespace App\Console\Commands;

use App\Models\FileAccessLog;
use Illuminate\Console\Command;

class PruneFileAccessLogs extends Command
{
    protected $signature = 'logs:prune-file-access {--days= : Retention window in days}';
    protected $description = 'Prune file access logs older than the retention window.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('app.file_access_log_retention_days', 90));
        if ($days <= 0) {
            $this->error('Retention days must be a positive integer.');
            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $count = FileAccessLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$count} file access log entries older than {$days} days.");
        return self::SUCCESS;
    }
}
