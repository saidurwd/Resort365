<?php

namespace Modules\Core\Actions;

use App\Support\Actions\Action;
use App\Support\Authorization\PermissionRegistry;
use Brick\Math\BigDecimal;
use Modules\Core\Exceptions\ApprovalRuleViolated;
use Modules\Core\Models\ApprovalStep;
use Modules\Core\Models\ApprovalWorkflow;

/**
 * Saves a workflow's steps and switch (Step 5.1). Steps are numbered in the order given; each needs a label, a
 * known permission and a minimum amount. Requests already waiting keep the steps frozen on them.
 */
class SaveApprovalWorkflow extends Action
{
    public function __construct(private readonly PermissionRegistry $permissions) {}

    /**
     * @param  list<array{label: string, permission: string, min_amount: string|null}>  $steps
     *
     * @throws ApprovalRuleViolated
     */
    public function handle(ApprovalWorkflow $workflow, bool $active, array $steps): ApprovalWorkflow
    {
        $known = $this->permissions->names();

        foreach ($steps as $index => $step) {
            if (trim($step['label']) === '' || ! in_array($step['permission'], $known, true)) {
                throw new ApprovalRuleViolated(__('Step :n needs a label and a permission of the system.', ['n' => $index + 1]));
            }

            if (BigDecimal::of($step['min_amount'] ?? '0')->isNegative()) {
                throw new ApprovalRuleViolated(__('Step :n: the amount cannot be negative.', ['n' => $index + 1]));
            }
        }

        if ($active && $steps === []) {
            throw new ApprovalRuleViolated(__('An active workflow needs at least one step.'));
        }

        return $this->transaction(function () use ($workflow, $active, $steps): ApprovalWorkflow {
            $workflow->forceFill(['is_active' => $active])->save();
            ApprovalStep::query()->where('approval_workflow_id', $workflow->id)->delete();

            foreach ($steps as $index => $step) {
                ApprovalStep::query()->create([
                    'approval_workflow_id' => $workflow->id, 'level' => $index + 1, 'label' => trim($step['label']), 'permission' => $step['permission'],
                    'min_amount' => (string) BigDecimal::of($step['min_amount'] ?? '0')->toScale(2),
                ]);
            }

            return $workflow->load('steps');
        });
    }
}
