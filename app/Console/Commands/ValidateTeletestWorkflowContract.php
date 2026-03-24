<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ValidateTeletestWorkflowContract extends Command
{
    protected $signature = 'iwosan:validate-teletest-workflow-contract';
    protected $description = 'Validate teletest workflow config integrity (statuses, transitions, actions, SLA).';

    public function handle(): int
    {
        $errors = [];

        $statuses = (array) config('teletest_workflow.statuses', []);
        $transitions = (array) config('teletest_workflow.allowed_transitions', []);
        $roleActions = (array) config('teletest_workflow.allowed_actions_by_role', []);
        $actionTargets = (array) config('teletest_workflow.action_targets', []);
        $slaWindows = (array) config('teletest_workflow.sla_windows', []);
        $terminalStatuses = array_map('strval', (array) config('teletest_workflow.terminal_statuses', []));

        if ($statuses === []) {
            $errors[] = 'Statuses map is empty.';
        }

        $knownStatuses = array_fill_keys(array_keys($statuses), true);

        foreach ($transitions as $fromStatus => $targets) {
            if (!isset($knownStatuses[$fromStatus])) {
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

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }
            $this->error('Teletest workflow contract validation failed.');
            return self::FAILURE;
        }

        $this->info('Teletest workflow contract validation passed.');
        return self::SUCCESS;
    }
}

