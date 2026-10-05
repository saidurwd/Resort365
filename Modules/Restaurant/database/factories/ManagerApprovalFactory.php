<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Models\ManagerApproval;

/**
 * @extends Factory<ManagerApproval>
 */
class ManagerApprovalFactory extends Factory
{
    use ResolvesProperty;

    protected $model = ManagerApproval::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'action' => 'session.close-variance',
            'permission' => 'restaurant.session.approve-variance',
            'requested_by' => fn (array $attributes): int => $this->userId((int) $attributes['property_id']),
            'approved_by' => fn (array $attributes): int => $this->userId((int) $attributes['property_id']),
            'expires_at' => now()->addMinutes(5),
        ];
    }
}
