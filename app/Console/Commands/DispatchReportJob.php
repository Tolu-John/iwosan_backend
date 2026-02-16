<?php

namespace App\Console\Commands;

use App\Jobs\GenerateReportJob;
use Illuminate\Console\Command;

class DispatchReportJob extends Command
{
    protected $signature = 'reports:generate {type} {--filter=*} {--requested_by=system}';
    protected $description = 'Dispatch a background job to generate a report.';

    public function handle(): int
    {
        $type = (string) $this->argument('type');
        $filters = $this->option('filter') ?: [];
        $requestedBy = (string) $this->option('requested_by');

        GenerateReportJob::dispatch($type, $filters, $requestedBy);

        $this->info('Report job dispatched.');
        return self::SUCCESS;
    }
}
