<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Enums\InstallmentStatus;
use Modules\Payroll\Models\Loan;
use Modules\Payroll\Models\LoanInstallment;

/**
 * @extends Factory<LoanInstallment>
 */
class LoanInstallmentFactory extends Factory
{
    protected $model = LoanInstallment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['loan_id' => Loan::factory(), 'sequence' => 1, 'due_month' => now()->addMonth()->startOfMonth()->toDateString(), 'amount' => '10000.00', 'status' => InstallmentStatus::Scheduled];
    }
}
