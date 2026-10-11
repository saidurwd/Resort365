<?php

namespace Modules\Core\Actions;

use App\Support\Actions\Action;
use Modules\Core\Contracts\ApprovalRegistry;
use Modules\Core\Models\ApprovalStep;
use Modules\Core\Models\ApprovalWorkflow;

/**
 * Gives the tenant the default workflow of every registered document type it lacks (Step 5.1). Workflows the
 * tenant already has, edited or not, are left alone.
 */
class SeedApprovalWorkflows extends Action
{
    public function __construct(private readonly ApprovalRegistry $registry) {}

    /**
     * @return int the number of workflows created
     */
    public function handle(): int
    {
        $created = 0;

        foreach ($this->registry->types() as $type) {
            if (ApprovalWorkflow::query()->where('document_type', $type->key)->exists()) {
                continue;
            }

            $this->transaction(function () use ($type): void {
                $workflow = ApprovalWorkflow::query()->create(['document_type' => $type->key, 'name' => $type->label, 'is_active' => true]);

                foreach ($type->steps as $index => $step) {
                    ApprovalStep::query()->create(['approval_workflow_id' => $workflow->id, 'level' => $index + 1, 'label' => $step->label, 'permission' => $step->permission, 'min_amount' => $step->minAmount]);
                }
            });
            $created++;
        }

        return $created;
    }
}
