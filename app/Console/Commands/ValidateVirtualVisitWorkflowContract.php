<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use ZipArchive;

class ValidateVirtualVisitWorkflowContract extends Command
{
    protected $signature = 'iwosan:validate-virtual-visit-workflow-contract';
    protected $description = 'Validate virtual visit workflow config integrity (statuses, transitions, actions, SLA, workbook sync).';

    public function handle(): int
    {
        $errors = [];

        $statuses = (array) config('virtual_visit_workflow.statuses', []);
        $transitions = (array) config('virtual_visit_workflow.allowed_transitions', []);
        $roleActions = (array) config('virtual_visit_workflow.allowed_actions_by_role', []);
        $actionTargets = (array) config('virtual_visit_workflow.action_targets', []);
        $slaWindows = (array) config('virtual_visit_workflow.sla_windows', []);
        $terminalStatuses = array_map('strval', (array) config('virtual_visit_workflow.terminal_statuses', []));
        $workbookStatuses = array_map('strval', (array) config('virtual_visit_workflow.workbook_statuses', []));
        $workbookPath = (string) config('virtual_visit_workflow.workbook_path', '');

        if ($statuses === []) {
            $errors[] = 'Statuses map is empty.';
        }

        if ($workbookStatuses === []) {
            $errors[] = 'Workbook statuses list is empty.';
        }

        $knownStatuses = array_fill_keys(array_keys($statuses), true);

        foreach ($transitions as $fromStatus => $targets) {
            if (!isset($knownStatuses[(string) $fromStatus])) {
                $errors[] = "Unknown transition source status '{$fromStatus}'.";
            }
            foreach ((array) $targets as $toStatus) {
                if (!isset($knownStatuses[(string) $toStatus])) {
                    $errors[] = "Unknown transition target status '{$toStatus}' from '{$fromStatus}'.";
                }
            }
        }

        foreach ($roleActions as $status => $roles) {
            if (!isset($knownStatuses[(string) $status])) {
                $errors[] = "Unknown role-action status '{$status}'.";
            }
            foreach ((array) $roles as $role => $actions) {
                if (!in_array((string) $role, ['patient', 'clinician', 'hospital', 'system'], true)) {
                    $errors[] = "Unknown role '{$role}' in status '{$status}'.";
                }
                foreach ((array) $actions as $action) {
                    if (!array_key_exists((string) $action, $actionTargets)) {
                        $errors[] = "Action '{$action}' in status '{$status}' role '{$role}' is missing in action_targets.";
                    }
                }
            }
        }

        foreach ($actionTargets as $action => $targetStatus) {
            $target = (string) $targetStatus;
            if ($target === '__stay' || str_starts_with($target, '__dynamic_')) {
                continue;
            }
            if (!isset($knownStatuses[$target])) {
                $errors[] = "Action target '{$target}' for action '{$action}' is not a known status.";
            }
        }

        foreach ($terminalStatuses as $status) {
            if (!isset($knownStatuses[$status])) {
                $errors[] = "Terminal status '{$status}' is not defined in statuses map.";
                continue;
            }
            $targets = (array) ($transitions[$status] ?? []);
            if ($targets !== []) {
                $errors[] = "Terminal status '{$status}' must not have outbound transitions.";
            }
        }

        foreach ($slaWindows as $status => $_) {
            if (!isset($knownStatuses[(string) $status])) {
                $errors[] = "SLA window references unknown status '{$status}'.";
            }
        }

        $statusKeys = array_keys($statuses);
        sort($statusKeys);
        $workbookSnapshot = array_values(array_unique(array_filter($workbookStatuses, static fn ($value) => trim((string) $value) !== '')));
        sort($workbookSnapshot);
        if ($statusKeys !== $workbookSnapshot) {
            $errors[] = 'Config statuses do not match workbook_statuses snapshot list.';
        }

        if ($workbookPath !== '') {
            $parsedStatuses = $this->parseWorkbookStatuses($workbookPath);
            if ($parsedStatuses['error'] !== null) {
                $this->warn("Workbook parse skipped: {$parsedStatuses['error']}");
            } else {
                $workbookLive = $parsedStatuses['statuses'];
                sort($workbookLive);
                if ($workbookLive !== $statusKeys) {
                    $errors[] = 'Config statuses do not match live workbook status keys.';
                }
            }
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }
            $this->error('Virtual visit workflow contract validation failed.');
            return self::FAILURE;
        }

        $this->info('Virtual visit workflow contract validation passed.');
        return self::SUCCESS;
    }

    /**
     * @return array{statuses: array<int, string>, error: ?string}
     */
    private function parseWorkbookStatuses(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            return ['statuses' => [], 'error' => 'ZipArchive extension is unavailable.'];
        }

        if (!is_file($path)) {
            return ['statuses' => [], 'error' => "Workbook file not found at '{$path}'."];
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return ['statuses' => [], 'error' => "Unable to open workbook '{$path}'."];
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetPath = $this->resolveFirstWorksheetPath($zip);
        if ($sheetPath === null) {
            $zip->close();
            return ['statuses' => [], 'error' => 'Unable to resolve first worksheet path in workbook.'];
        }

        $sheetXml = $zip->getFromName($sheetPath);
        $zip->close();

        if ($sheetXml === false) {
            return ['statuses' => [], 'error' => "Unable to read worksheet '{$sheetPath}' from workbook."];
        }

        $statuses = $this->extractStatusColumnValues($sheetXml, $sharedStrings);
        if ($statuses === []) {
            return ['statuses' => [], 'error' => 'No status keys found in workbook column B.'];
        }

        return ['statuses' => array_values(array_unique($statuses)), 'error' => null];
    }

    /**
     * @return array<int, string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $content = $zip->getFromName('xl/sharedStrings.xml');
        if ($content === false) {
            return [];
        }

        $xml = simplexml_load_string($content);
        if ($xml === false) {
            return [];
        }

        $items = $xml->xpath('//*[local-name()="si"]');
        if ($items === false) {
            return [];
        }

        $strings = [];
        foreach ($items as $item) {
            $fragments = $item->xpath('.//*[local-name()="t"]');
            if ($fragments === false) {
                $strings[] = '';
                continue;
            }
            $value = '';
            foreach ($fragments as $fragment) {
                $value .= (string) $fragment;
            }
            $strings[] = trim($value);
        }

        return $strings;
    }

    private function resolveFirstWorksheetPath(ZipArchive $zip): ?string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml === false || $relsXml === false) {
            return null;
        }

        $workbook = simplexml_load_string($workbookXml);
        $rels = simplexml_load_string($relsXml);
        if ($workbook === false || $rels === false) {
            return null;
        }

        $sheetNodes = $workbook->xpath('//*[local-name()="sheets"]/*[local-name()="sheet"]');
        if ($sheetNodes === false || $sheetNodes === []) {
            return null;
        }

        $attributes = $sheetNodes[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $relationshipId = trim((string) ($attributes['id'] ?? ''));
        if ($relationshipId === '') {
            return null;
        }

        $relationshipNodes = $rels->xpath("//*[local-name()='Relationship'][@Id='{$relationshipId}']");
        if ($relationshipNodes === false || $relationshipNodes === []) {
            return null;
        }

        $target = (string) $relationshipNodes[0]['Target'];
        if ($target === '') {
            return null;
        }

        if (!str_starts_with($target, 'xl/')) {
            $target = 'xl/' . ltrim($target, '/');
        }

        return $target;
    }

    /**
     * Reads status keys from column B where row 1 is the header.
     *
     * @param array<int, string> $sharedStrings
     * @return array<int, string>
     */
    private function extractStatusColumnValues(string $sheetXml, array $sharedStrings): array
    {
        $sheet = simplexml_load_string($sheetXml);
        if ($sheet === false) {
            return [];
        }

        $rows = $sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]');
        if ($rows === false) {
            return [];
        }

        $statuses = [];
        foreach ($rows as $row) {
            $rowIndex = (int) ($row['r'] ?? 0);
            if ($rowIndex <= 1) {
                continue;
            }

            $cells = $row->xpath("*[local-name()='c'][starts-with(@r, 'B')]");
            if ($cells === false || $cells === []) {
                continue;
            }

            $cell = $cells[0];
            $type = (string) ($cell['t'] ?? '');
            $valueNodes = $cell->xpath("*[local-name()='v']");
            if ($valueNodes === false || $valueNodes === []) {
                continue;
            }

            $raw = trim((string) $valueNodes[0]);
            if ($raw === '') {
                continue;
            }

            if ($type === 's') {
                $index = (int) $raw;
                $value = trim((string) ($sharedStrings[$index] ?? ''));
            } else {
                $value = trim($raw);
            }

            if ($value === '') {
                continue;
            }

            $statuses[] = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $value) ?? $value);
            $statuses[count($statuses) - 1] = trim($statuses[count($statuses) - 1], '_');
        }

        return array_values(array_unique(array_filter($statuses, static fn ($item) => $item !== '')));
    }
}
