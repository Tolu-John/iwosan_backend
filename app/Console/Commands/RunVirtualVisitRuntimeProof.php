<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class RunVirtualVisitRuntimeProof extends Command
{
    protected $signature = 'iwosan:runtime-proof:virtual';
    protected $description = 'Execute virtual-visit runtime proof and write CSV/summary artifacts.';

    public function handle(): int
    {
        $this->info('Running virtual-visit runtime proof test...');

        $process = new Process([
            'php',
            'artisan',
            'test',
            'tests/Feature/VirtualVisitRuntimeProofReportTest.php',
        ], base_path());
        $process->setTimeout(600);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });
        $exit = $process->getExitCode() ?? 1;

        $summaryPath = base_path('../execution_plans/VIRTUAL_VISIT_RUNTIME_PROOF_SUMMARY.md');
        $reportPath = base_path('../execution_plans/VIRTUAL_VISIT_RUNTIME_PROOF_REPORT.csv');

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
            $this->error('Virtual-visit runtime proof failed.');
            return self::FAILURE;
        }

        $this->info('Virtual-visit runtime proof completed successfully.');
        return self::SUCCESS;
    }
}
