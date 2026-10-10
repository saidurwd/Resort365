<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Database\Factories\Concerns\ResolvesProperty;
use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Enums\VoucherStatus;
use Modules\Accounting\Enums\VoucherType;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Voucher;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    use ResolvesProperty;

    protected $model = Voucher::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(),
            'voucher_no' => 'EV-'.fake()->unique()->numerify('####-#####'),
            'type' => VoucherType::Expense,
            'voucher_date' => now()->toDateString(),
            'account_id' => Account::factory()->state(['type' => AccountType::Expense]),
            'cash_account_id' => Account::factory()->state(['type' => AccountType::Asset]),
            'description' => fake()->sentence(4),
            'amount' => '1000.00',
            'tax_amount' => '0.00',
            'status' => VoucherStatus::Posted,
        ];
    }
}
