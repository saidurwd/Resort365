<?php

namespace Modules\Platform\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Platform\Enums\BillingPeriod;
use Modules\Platform\Enums\SubscriptionStatus;
use Modules\Platform\Models\Plan;
use Modules\Platform\Models\Subscription;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(), 'plan_id' => Plan::factory(), 'billing_period' => BillingPeriod::Monthly, 'status' => SubscriptionStatus::Active,
            'trial_ends_at' => null, 'current_period_start' => now()->startOfDay(), 'current_period_end' => now()->addMonthNoOverflow()->subDay()->startOfDay(),
            'cancel_at_period_end' => false, 'gateway' => 'manual',
        ];
    }

    public function trialing(): static
    {
        return $this->state(['status' => SubscriptionStatus::Trialing, 'trial_ends_at' => now()->addDays(14), 'current_period_start' => null, 'current_period_end' => null]);
    }
}
