<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ExportTeletestUatMatrix extends Command
{
    protected $signature = 'iwosan:export-teletest-uat-matrix {--output= : Absolute or relative output CSV path}';
    protected $description = 'Export teletest UAT execution matrix CSV from teletest_workflow config.';

    public function handle(): int
    {
        $statuses = (array) config('teletest_workflow.statuses', []);
        $actionsByRole = (array) config('teletest_workflow.allowed_actions_by_role', []);
        $transitions = (array) config('teletest_workflow.allowed_transitions', []);

        if ($statuses === []) {
            $this->error('No teletest statuses found in config(teletest_workflow.statuses).');
            return self::FAILURE;
        }

        $defaultPath = base_path('../execution_plans/TELETEST_UAT_EXECUTION_MATRIX.csv');
        $output = (string) ($this->option('output') ?: $defaultPath);
        $resolvedPath = str_starts_with($output, '/')
            ? $output
            : base_path($output);

        $dir = dirname($resolvedPath);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            $this->error("Unable to create output directory: {$dir}");
            return self::FAILURE;
        }

        $stream = fopen($resolvedPath, 'w');
        if ($stream === false) {
            $this->error("Unable to open output file: {$resolvedPath}");
            return self::FAILURE;
        }

        fputcsv($stream, [
            'status_key',
            'label',
            'patient_actions',
            'clinician_actions',
            'hospital_actions',
            'next_statuses',
            'terminal',
        ]);

        foreach ($statuses as $statusKey => $statusMeta) {
            $meta = is_array($statusMeta) ? $statusMeta : [];
            $roleActions = (array) ($actionsByRole[$statusKey] ?? []);
            $next = array_values(array_map('strval', (array) ($transitions[$statusKey] ?? [])));
            $isTerminal = (bool) ($meta['terminal'] ?? false);
            if (!$isTerminal && $next === []) {
                $isTerminal = true;
            }

            fputcsv($stream, [
                $statusKey,
                (string) ($meta['label'] ?? $statusKey),
                $this->joinActions((array) ($roleActions['patient'] ?? [])),
                $this->joinActions((array) ($roleActions['clinician'] ?? [])),
                $this->joinActions((array) ($roleActions['hospital'] ?? [])),
                implode(' | ', $next),
                $isTerminal ? 'yes' : 'no',
            ]);
        }

        fclose($stream);

        $this->info("Teletest UAT execution matrix exported: {$resolvedPath}");
        $this->info('Rows exported: '.count($statuses));

        return self::SUCCESS;
    }

    /**
     * @param array<int, mixed> $actions
     */
    private function joinActions(array $actions): string
    {
        return implode(' | ', array_values(array_map('strval', $actions)));
    }
}
