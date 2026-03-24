<?php

namespace Tests\Unit;

use Tests\TestCase;

class VirtualVisitWorkflowContractTest extends TestCase
{
    public function test_all_transition_nodes_reference_known_statuses(): void
    {
        $statuses = (array) config('virtual_visit_workflow.statuses', []);
        $transitions = (array) config('virtual_visit_workflow.allowed_transitions', []);
        $known = array_fill_keys(array_keys($statuses), true);

        foreach (array_keys($transitions) as $fromStatus) {
            $this->assertArrayHasKey(
                $fromStatus,
                $known,
                "Transition source status '{$fromStatus}' must exist in statuses."
            );
        }

        foreach ($transitions as $fromStatus => $targets) {
            foreach ((array) $targets as $toStatus) {
                $this->assertArrayHasKey(
                    (string) $toStatus,
                    $known,
                    "Transition target status '{$toStatus}' from '{$fromStatus}' must exist in statuses."
                );
            }
        }
    }

    public function test_role_actions_reference_known_statuses_and_actions(): void
    {
        $statuses = (array) config('virtual_visit_workflow.statuses', []);
        $roleActions = (array) config('virtual_visit_workflow.allowed_actions_by_role', []);
        $actionTargets = (array) config('virtual_visit_workflow.action_targets', []);
        $known = array_fill_keys(array_keys($statuses), true);

        foreach ($roleActions as $status => $roles) {
            $this->assertArrayHasKey(
                $status,
                $known,
                "Role-actions status '{$status}' must exist in statuses."
            );

            foreach ((array) $roles as $role => $actions) {
                foreach ((array) $actions as $action) {
                    $this->assertArrayHasKey(
                        (string) $action,
                        $actionTargets,
                        "Action '{$action}' for status '{$status}' and role '{$role}' must be declared in action_targets."
                    );
                }
            }
        }
    }

    public function test_action_targets_reference_known_status_or_allowed_markers(): void
    {
        $statuses = (array) config('virtual_visit_workflow.statuses', []);
        $actionTargets = (array) config('virtual_visit_workflow.action_targets', []);
        $known = array_fill_keys(array_keys($statuses), true);

        foreach ($actionTargets as $action => $targetStatus) {
            $target = (string) $targetStatus;
            if ($target === '__stay' || str_starts_with($target, '__dynamic_')) {
                continue;
            }

            $this->assertArrayHasKey(
                $target,
                $known,
                "Action '{$action}' target '{$target}' must be a known status."
            );
        }
    }

    public function test_terminal_statuses_have_no_outbound_transitions(): void
    {
        $terminalStatuses = array_map('strval', (array) config('virtual_visit_workflow.terminal_statuses', []));
        $transitions = (array) config('virtual_visit_workflow.allowed_transitions', []);

        foreach ($terminalStatuses as $status) {
            $targets = (array) ($transitions[$status] ?? []);
            $this->assertCount(
                0,
                $targets,
                "Terminal status '{$status}' must not have outbound transitions."
            );
        }
    }

    public function test_workbook_snapshot_statuses_match_config_statuses(): void
    {
        $statuses = array_keys((array) config('virtual_visit_workflow.statuses', []));
        $workbookStatuses = array_values(array_unique(array_map('strval', (array) config('virtual_visit_workflow.workbook_statuses', []))));

        sort($statuses);
        sort($workbookStatuses);

        $this->assertSame(
            $statuses,
            $workbookStatuses,
            'virtual_visit_workflow.workbook_statuses must match statuses map keys.'
        );
    }

    public function test_validation_command_passes(): void
    {
        $this->artisan('iwosan:validate-virtual-visit-workflow-contract')
            ->expectsOutput('Virtual visit workflow contract validation passed.')
            ->assertSuccessful();
    }
}

