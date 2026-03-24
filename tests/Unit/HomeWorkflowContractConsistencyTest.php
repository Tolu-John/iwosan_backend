<?php

namespace Tests\Unit;

use App\Services\AppointmentService;
use ReflectionClass;
use Tests\TestCase;

class HomeWorkflowContractConsistencyTest extends TestCase
{
    public function test_role_actions_resolve_to_valid_transition_targets(): void
    {
        $roleActions = (array) config('home_visit_workflow.role_actions', []);
        $transitions = (array) config('home_visit_workflow.transitions', []);
        $actionMap = $this->homeActionMap();

        foreach ($roleActions as $fromStatus => $roles) {
            $allowedTargets = (array) ($transitions[$fromStatus] ?? []);
            foreach ((array) $roles as $actions) {
                foreach ((array) $actions as $actionKey) {
                    $actionKey = strtolower(trim((string) $actionKey));
                    if ($actionKey === '' || !isset($actionMap[$actionKey])) {
                        continue;
                    }
                    $target = (string) $actionMap[$actionKey];
                    if ($target === '__stay' || $target === '__dynamic_request_changes' || $target === $fromStatus) {
                        continue;
                    }

                    // Contextual target behavior handled in service:
                    // request_changes from awaiting_hospital_approval stays in place.
                    if ($actionKey === 'request_changes' && $fromStatus === 'awaiting_hospital_approval') {
                        continue;
                    }

                    // Contextual target behavior handled in service:
                    // complete_visit can branch to admission_recommended.
                    if ($actionKey === 'complete_visit') {
                        $isValidCompleteVisitTarget =
                            in_array('visit_completed', $allowedTargets, true) ||
                            in_array('admission_recommended', $allowedTargets, true);
                        $this->assertTrue(
                            $isValidCompleteVisitTarget,
                            "Status '{$fromStatus}' with action '{$actionKey}' has invalid completion branching."
                        );
                        continue;
                    }

                    $this->assertTrue(
                        in_array($target, $allowedTargets, true),
                        "Status '{$fromStatus}' allows action '{$actionKey}' but target '{$target}' is not in transitions."
                    );
                }
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function homeActionMap(): array
    {
        $reflection = new ReflectionClass(AppointmentService::class);
        $constant = $reflection->getReflectionConstant('HOME_ACTION_STATUS_MAP');
        $value = $constant?->getValue();
        return is_array($value) ? $value : [];
    }
}
