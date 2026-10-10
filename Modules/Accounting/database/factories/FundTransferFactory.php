<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Database\Factories\Concerns\ResolvesProperty;
use Modules\Accounting\Enums\VoucherStatus;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\FundTransfer;

/**
 * @extends Factory<FundTransfer>
 */
class FundTransferFactory extends Factory
{
    use ResolvesProperty;

    protected $model = FundTransfer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(), 'transfer_no' => 'TR-'.fake()->unique()->numerify('####-#####'), 'transfer_date' => now()->toDateString(),
            'from_bank_account_id' => BankAccount::factory(), 'to_bank_account_id' => BankAccount::factory(), 'amount' => '1000.00', 'status' => VoucherStatus::Posted,
        ];
    }
}
