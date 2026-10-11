<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\ApprovalDelegation;

/**
 * @extends Factory<ApprovalDelegation>
 */
class ApprovalDelegationFactory extends Factory
{
    protected $model = ApprovalDelegation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['user_id' => 1, 'delegate_user_id' => 2, 'starts_on' => now()->toDateString(), 'ends_on' => now()->addWeek()->toDateString()];
    }
}
