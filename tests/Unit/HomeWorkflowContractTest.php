<?php

namespace Tests\Unit;

use Tests\TestCase;

class HomeWorkflowContractTest extends TestCase
{
    public function test_all_transition_nodes_reference_known_statuses(): void
    {
        $statuses = (array) config('home_visit_workflow.statuses', []);
        $legacy = array_map('strval', (array) config('home_visit_workflow.legacy_statuses', []));
        $transitions = (array) config('home_visit_workflow.transitions', []);

        $statusKeys = array_keys($statuses);
        $known = array_values(array_unique(array_merge($statusKeys, $legacy)));
        $statusLookup = array_fill_keys($known, true);

        foreach (array_keys($transitions) as $fromStatus) {
            $this->assertArrayHasKey(
                $fromStatus,
                $statusLookup,
                "Transition source status '{$fromStatus}' must exist in statuses."
            );
        }

        foreach ($transitions as $fromStatus => $targets) {
            foreach ((array) $targets as $toStatus) {
                $this->assertArrayHasKey(
                    $toStatus,
                    $statusLookup,
                    "Transition target status '{$toStatus}' from '{$fromStatus}' must exist in statuses."
                );
            }
        }
    }

    public function test_role_actions_only_defined_for_known_statuses(): void
    {
        $statuses = (array) config('home_visit_workflow.statuses', []);
        $roleActions = (array) config('home_visit_workflow.role_actions', []);
        $statusLookup = array_fill_keys(array_keys($statuses), true);

        foreach (array_keys($roleActions) as $status) {
            $this->assertArrayHasKey(
                $status,
                $statusLookup,
                "Role-actions status '{$status}' must exist in statuses."
            );
        }
    }

    public function test_terminal_statuses_have_no_outbound_transitions(): void
    {
        $statuses = (array) config('home_visit_workflow.statuses', []);
        $transitions = (array) config('home_visit_workflow.transitions', []);

        foreach ($statuses as $status => $meta) {
            if (!($meta['terminal'] ?? false)) {
                continue;
            }

            $targets = (array) ($transitions[$status] ?? []);
            $this->assertCount(
                0,
                $targets,
                "Terminal status '{$status}' must not have outbound transitions."
            );
        }
    }

    public function test_role_actions_are_declared_in_action_targets(): void
    {
        $roleActions = (array) config('home_visit_workflow.role_actions', []);
        $actionTargets = (array) config('home_visit_workflow.action_targets', []);

        foreach ($roleActions as $status => $roles) {
            foreach ((array) $roles as $role => $actions) {
                foreach ((array) $actions as $action) {
                    $this->assertArrayHasKey(
                        $action,
                        $actionTargets,
                        "Action '{$action}' in role_actions for status '{$status}' and role '{$role}' must be declared in action_targets."
                    );
                }
            }
        }
    }

    public function test_action_targets_reference_known_status_or_stay_marker(): void
    {
        $statuses = (array) config('home_visit_workflow.statuses', []);
        $legacy = array_map('strval', (array) config('home_visit_workflow.legacy_statuses', []));
        $actionTargets = (array) config('home_visit_workflow.action_targets', []);
        $known = array_fill_keys(array_values(array_unique(array_merge(array_keys($statuses), $legacy))), true);

        foreach ($actionTargets as $action => $targetStatus) {
            if ($targetStatus === '__stay' || $targetStatus === '__dynamic_request_changes') {
                continue;
            }

            $this->assertArrayHasKey(
                $targetStatus,
                $known,
                "Action '{$action}' target '{$targetStatus}' must be a known status."
            );
        }
    }

    public function test_default_action_targets_are_reachable_from_role_action_statuses(): void
    {
        $roleActions = (array) config('home_visit_workflow.role_actions', []);
        $actionTargets = (array) config('home_visit_workflow.action_targets', []);
        $transitions = (array) config('home_visit_workflow.transitions', []);

        // Dynamic actions can resolve to multiple targets based on payload/context.
        $dynamicActionTargetsByStatus = [
            'awaiting_hospital_approval' => [
                'request_changes' => ['awaiting_hospital_approval'],
            ],
            'home_admission_quote_pending_hospital' => [
                'request_changes' => ['admission_revision_requested'],
            ],
            'in_progress' => [
                'complete_visit' => ['visit_completed', 'admission_recommended'],
                'escalate' => ['escalation_open', 'escalation_in_transfer'],
                'open_escalation' => ['escalation_open', 'escalation_in_transfer'],
            ],
            'scheduled' => [
                'open_escalation' => ['escalation_open', 'escalation_in_transfer'],
            ],
            'en_route' => [
                'open_escalation' => ['escalation_open', 'escalation_in_transfer'],
            ],
            'arrived' => [
                'open_escalation' => ['escalation_open', 'escalation_in_transfer'],
            ],
            'home_admitted_active' => [
                'open_escalation' => ['escalation_open', 'escalation_in_transfer'],
            ],
            'payment_due' => [
                'open_escalation' => ['escalation_open', 'escalation_in_transfer'],
            ],
            'payment_overdue' => [
                'open_escalation' => ['escalation_open', 'escalation_in_transfer'],
            ],
            'care_paused_non_critical' => [
                'open_escalation' => ['escalation_open', 'escalation_in_transfer'],
            ],
        ];

        foreach ($roleActions as $status => $roles) {
            $allowedTargets = (array) ($transitions[$status] ?? []);

            foreach ((array) $roles as $actions) {
                foreach ((array) $actions as $action) {
                    $targets = $dynamicActionTargetsByStatus[$status][$action]
                        ?? [($actionTargets[$action] ?? null)];

                    foreach ($targets as $target) {
                        if (!$target || $target === '__stay' || $target === $status) {
                            continue;
                        }

                        $this->assertContains(
                            $target,
                            $allowedTargets,
                            "Action '{$action}' configured for status '{$status}' resolves to '{$target}' which is not in transitions for '{$status}'."
                        );
                    }
                }
            }
        }
    }

    public function test_hospital_has_no_scheduled_home_visit_actions(): void
    {
        $roleActions = (array) config('home_visit_workflow.role_actions', []);
        $scheduled = (array) ($roleActions['scheduled'] ?? []);
        $hospital = (array) ($scheduled['hospital'] ?? []);

        $this->assertSame(
            [],
            $hospital,
            "Hospital must not have direct actions in home_visit 'scheduled' status."
        );
    }
}
