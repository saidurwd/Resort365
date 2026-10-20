<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Database\Factories\Concerns\ResolvesReferences;
use Modules\Payroll\Enums\LoanKind;
use Modules\Payroll\Enums\LoanStatus;
use Modules\Payroll\Enums\PayMethod;
use Modules\Payroll\Models\Loan;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    use ResolvesReferences;

    protected $model = Loan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(), 'loan_no' => 'LN-'.fake()->unique()->numerify('####-#####'), 'employee_id' => $this->employeeId(), 'kind' => LoanKind::Loan, 'principal' => '60000.00', 'installments_count' => 6,
            'first_month' => now()->addMonth()->startOfMonth()->toDateString(), 'disbursed_on' => now()->toDateString(), 'payment_method' => PayMethod::Cash, 'status' => LoanStatus::Active,
        ];
    }
}
