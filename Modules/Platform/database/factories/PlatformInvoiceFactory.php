<?php

namespace Modules\Platform\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Platform\Enums\PlatformInvoiceStatus;
use Modules\Platform\Models\PlatformInvoice;

/**
 * @extends Factory<PlatformInvoice>
 */
class PlatformInvoiceFactory extends Factory
{
    protected $model = PlatformInvoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'INV-'.now()->year.'-'.fake()->unique()->numerify('9####'), 'tenant_id' => Tenant::factory(), 'subscription_id' => null, 'status' => PlatformInvoiceStatus::Open,
            'description' => 'Professional plan, monthly', 'period_start' => now()->startOfDay(), 'period_end' => now()->addMonthNoOverflow()->subDay()->startOfDay(),
            'issued_on' => now()->startOfDay(), 'due_on' => now()->addDays(7)->startOfDay(), 'currency_code' => 'USD', 'amount' => '149.00', 'paid_amount' => '0.00',
        ];
    }
}
