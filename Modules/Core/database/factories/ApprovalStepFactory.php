<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\ApprovalStep;
use Modules\Core\Models\ApprovalWorkflow;

/**
 * @extends Factory<ApprovalStep>
 */
class ApprovalStepFactory extends Factory
{
    protected $model = ApprovalStep::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['approval_workflow_id' => ApprovalWorkflow::factory(), 'level' => 1, 'label' => 'General Manager', 'permission' => 'core.approval.manage', 'min_amount' => '0.00'];
    }
}
