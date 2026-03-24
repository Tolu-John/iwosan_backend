<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class RunTeletestRuntimeProof extends Command
{
    protected $signature = 'iwosan:runtime-proof:teletest';
    protected $description = 'Execute teletest runtime proof and write CSV/summary artifacts.';

    public function handle(): int
    {
        $this->info('Running teletest runtime proof test...');

        $process = new Process([
            'php',
            'artisan',
            'test',
            'tests/Feature/TeletestRuntimeProofReportTest.php',
        ], base_path());
        $process->setTimeout(600);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });
        $exit = $process->getExitCode() ?? 1;

        $summaryPath = base_path('../execution_plans/TELETEST_RUNTIME_PROOF_SUMMARY.md');
        $reportPath = base_path('../execution_plans/TELETEST_RUNTIME_PROOF_REPORT.csv');

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
            $this->error('Teletest runtime proof failed.');
            return self::FAILURE;
        }

        $this->info('Teletest runtime proof completed successfully.');
        return self::SUCCESS;
    }
}
