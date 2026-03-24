<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class RunHomeVisitRuntimeProof extends Command
{
    protected $signature = 'iwosan:runtime-proof:home';
    protected $description = 'Execute home-visit runtime proof and write CSV/summary artifacts.';

    public function handle(): int
    {
        $this->info('Running home-visit runtime proof test...');

        $process = new Process([
            'php',
            'artisan',
            'test',
            'tests/Feature/HomeVisitRuntimeProofReportTest.php',
        ], base_path());
        $process->setTimeout(600);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });
        $exit = $process->getExitCode() ?? 1;

        $summaryPath = base_path('../execution_plans/HOME_VISIT_RUNTIME_PROOF_SUMMARY.md');
        $reportPath = base_path('../execution_plans/HOME_VISIT_RUNTIME_PROOF_REPORT.csv');

        if (is_file($summaryPath)) {
            $this->line('');
            $this->line(trim((string) file_get_contents($summaryPath)));
        } else {
            $this->warn('Summary file was not generated.');
        }

        $this->line('');
        $this->line("Summary: {$summaryPath}");
        $this->line("Report: {$reportPath}");

        if ($exit !== 0) {
            $this->error('Home-visit runtime proof failed.');
            return self::FAILURE;
        }

        $this->info('Home-visit runtime proof completed successfully.');
        return self::SUCCESS;
    }
}

