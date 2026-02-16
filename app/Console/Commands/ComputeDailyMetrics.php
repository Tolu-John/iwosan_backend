<?php

namespace App\Console\Commands;

use App\Jobs\ComputeDailyMetricsJob;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ComputeDailyMetrics extends Command
{
    protected $signature = 'metrics:daily {--date= : YYYY-MM-DD}';
    protected $description = 'Compute daily metric summaries from metric_events.';

    public function handle(): int
    {
        $date = $this->option('date') ?: Carbon::yesterday()->toDateString();
        ComputeDailyMetricsJob::dispatch($date);

        $this->info('Dispatched daily metrics job for '.$date);
        return self::SUCCESS;
    }
}
