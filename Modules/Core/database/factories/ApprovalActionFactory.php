<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\ApprovalAction;
use Modules\Core\Models\ApprovalRequest;

/**
 * @extends Factory<ApprovalAction>
 */
class ApprovalActionFactory extends Factory
{
    protected $model = ApprovalAction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['approval_request_id' => ApprovalRequest::factory(), 'level' => 1, 'action' => 'approved', 'user_id' => 1, 'comment' => null, 'acted_at' => now()];
    }
}
