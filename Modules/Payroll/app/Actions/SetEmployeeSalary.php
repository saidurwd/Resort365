<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Modules\HR\Contracts\EmployeeDirectory;
use Modules\HR\DTOs\EmployeeSummary;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\EmployeeSalary;
use Modules\Payroll\Models\SalaryStructure;
use Modules\Payroll\Models\TaxTable;
use Modules\Payroll\Services\TaxTableResolver;

/**
 * Gives an employee a salary from a date (Step 6.4): the monthly basic, the salary structure, any amounts set for this
 * employee, and the tax category and area. The record before it ends the day before, and it ends the day before the next
 * one. History is kept: a new record never changes a record that has started, and cannot be dated before one that has.
 */
class SetEmployeeSalary extends Action
{
    public function __construct(
        private readonly EmployeeDirectory $employees,
        private readonly TaxTableResolver $taxTables,
    ) {}

    /**
     * @param  array<string, mixed>  $data  salary_structure_id, effective_from, basic, amounts (code => amount), tax_category, tax_area, notes
     *
     * @throws PayrollRuleViolated
     */
    public function handle(int $employeeId, array $data, ?int $userId, ?string $today = null): EmployeeSalary
    {
        if (! $this->employees->find($employeeId) instanceof EmployeeSummary) {
            throw new PayrollRuleViolated(__('That employee does not exist, or you cannot see them.'));
        }

        $from = (string) $data['effective_from'];
        $today ??= now()->toDateString();
        $basic = $data['basic'] ?? null;

        if (! is_numeric($basic) || (float) $basic <= 0) {
            throw new PayrollRuleViolated(__('Enter the monthly basic salary.'));
        }

        $structure = SalaryStructure::query()->find((int) ($data['salary_structure_id'] ?? 0));

        if (! $structure instanceof SalaryStructure || ! $structure->is_active) {
            throw new PayrollRuleViolated(__('Choose an active salary structure.'));
        }

        $category = trim((string) ($data['tax_category'] ?? 'general'));
        $area = trim((string) ($data['tax_area'] ?? 'other'));
        $this->checkTax($from, $category, $area);

        $amounts = [];

        foreach ((array) ($data['amounts'] ?? []) as $code => $amount) {
            if ($amount === null || $amount === '') {
                continue;
            }

            if (! is_numeric($amount) || (float) $amount < 0) {
                throw new PayrollRuleViolated(__('Enter a number for :code.', ['code' => strtoupper((string) $code)]));
            }

            $amounts[strtoupper((string) $code)] = number_format((float) $amount, 2, '.', '');
        }

        return $this->transaction(function () use ($employeeId, $data, $userId, $from, $today, $basic, $structure, $category, $area, $amounts): EmployeeSalary {
            $records = EmployeeSalary::query()->where('employee_id', $employeeId)->lockForUpdate()->orderBy('effective_from')->get();

            if ($records->contains(fn (EmployeeSalary $record): bool => $record->effective_from->toDateString() === $from)) {
                throw new PayrollRuleViolated(__('This employee already has a salary from :date.', ['date' => CarbonImmutable::parse($from)->format('d M Y')]));
            }

            if ($records->contains(fn (EmployeeSalary $record): bool => $record->effective_from->toDateString() > $from && $record->effective_from->toDateString() <= $today)) {
                throw new PayrollRuleViolated(__('A later salary has already started, so a salary cannot be dated before it.'));
            }

            $previous = $records->last(fn (EmployeeSalary $record): bool => $record->effective_from->toDateString() < $from);
            $next = $records->first(fn (EmployeeSalary $record): bool => $record->effective_from->toDateString() > $from);
            $previous?->forceFill(['effective_to' => CarbonImmutable::parse($from)->subDay()->toDateString()])->save();

            return EmployeeSalary::query()->create([
                'employee_id' => $employeeId, 'salary_structure_id' => $structure->id, 'effective_from' => $from, 'effective_to' => $next !== null ? $next->effective_from->subDay()->toDateString() : null,
                'basic' => number_format((float) $basic, 2, '.', ''), 'amounts' => $amounts === [] ? null : $amounts, 'tax_category' => $category, 'tax_area' => $area,
                'notes' => isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null, 'created_by' => $userId,
            ]);
        }, attempts: 3);
    }

    /**
     * @throws PayrollRuleViolated
     */
    private function checkTax(string $date, string $category, string $area): void
    {
        $table = $this->taxTables->forDate($date);

        if (! $table instanceof TaxTable) {
            return;
        }

        if (! collect($table->thresholds)->contains('code', $category)) {
            throw new PayrollRuleViolated(__('":category" is not a taxpayer category of the tax table in force.', ['category' => $category]));
        }

        if ($table->minimum_tax !== [] && ! collect($table->minimum_tax)->contains('code', $area)) {
            throw new PayrollRuleViolated(__('":area" is not a tax area of the tax table in force.', ['area' => $area]));
        }
    }
}
