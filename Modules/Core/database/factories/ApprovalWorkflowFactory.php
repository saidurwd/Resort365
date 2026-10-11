<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\ApprovalWorkflow;

/**
 * @extends Factory<ApprovalWorkflow>
 */
class ApprovalWorkflowFactory extends Factory
{
    protected $model = ApprovalWorkflow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['document_type' => 'doc_'.fake()->unique()->lexify('????'), 'name' => fake()->words(2, true), 'is_active' => true];
    }
}
