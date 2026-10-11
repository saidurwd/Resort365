<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Enums\ApprovalStatus;
use Modules\Core\Models\ApprovalRequest;

/**
 * @extends Factory<ApprovalRequest>
 */
class ApprovalRequestFactory extends Factory
{
    protected $model = ApprovalRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_type' => 'doc_'.fake()->lexify('????'), 'reference' => fake()->sentence(3), 'amount' => '1000.00', 'steps' => [['level' => 1, 'label' => 'General Manager', 'permission' => 'core.approval.manage']],
            'current_level' => 1, 'status' => ApprovalStatus::Pending, 'requested_by' => 1,
        ];
    }
}
