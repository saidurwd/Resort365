<?php

namespace Modules\HR\Actions;

use App\Support\Actions\Action;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\HR\Enums\EmployeeStatus;
use Modules\HR\Enums\EmploymentType;
use Modules\HR\Enums\Gender;
use Modules\HR\Enums\HistoryEvent;
use Modules\HR\Enums\MaritalStatus;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Designation;
use Modules\HR\Models\Employee;
use Modules\HR\Services\EmployeeHistoryWriter;
use Modules\HR\Services\NationalIdHasher;

/**
 * Creates or changes an employee (Step 6.1). A new one takes the next `EMP-0001`, lands in the current property (or the
 * `property_id` given by console code), starts on probation when a probation end is set, and gets a "joined" history entry.
 * The national ID, passport and bank account number are kept when left empty. Status and exit are changed by the lifecycle
 * actions only.
 */
class SaveEmployee extends Action
{
    public function __construct(
        private readonly DocumentNumbers $numbers,
        private readonly NationalIdHasher $hasher,
        private readonly EmployeeHistoryWriter $history,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws HrRuleViolated
     */
    public function handle(?Employee $employee, array $data, ?int $userId = null): Employee
    {
        if ($employee instanceof Employee && $employee->isExited()) {
            throw new HrRuleViolated(__(':name has left, so the record can no longer be changed.', ['name' => $employee->full_name]));
        }

        return $this->transaction(function () use ($employee, $data, $userId): Employee {
            $values = $this->values($data);
            $this->check($employee, $values);

            $nationalId = isset($data['national_id']) && trim((string) $data['national_id']) !== '' ? trim((string) $data['national_id']) : null;

            if ($nationalId !== null) {
                $hash = $this->hasher->hash($nationalId);

                if ($hash !== null && Employee::query()->where('national_id_hash', $hash)->when($employee instanceof Employee, fn ($query) => $query->whereKeyNot($employee?->id))->exists()) {
                    throw new HrRuleViolated(__('Another employee already has this national ID.'));
                }

                $values['national_id'] = $nationalId;
                $values['national_id_hash'] = $hash;
            }

            foreach (['passport_no', 'bank_account_no'] as $key) {
                if (isset($data[$key]) && trim((string) $data[$key]) !== '') {
                    $values[$key] = trim((string) $data[$key]);
                }
            }

            if ($employee instanceof Employee) {
                $employee->fill($values)->save();

                return $employee;
            }

            $values['employee_no'] = $this->numbers->next('employee');
            $values['status'] = $values['probation_ends_on'] !== null ? EmployeeStatus::OnProbation : EmployeeStatus::Active;

            if (isset($data['property_id']) && $data['property_id'] !== '') {
                $values['property_id'] = (int) $data['property_id'];
            }

            $created = Employee::query()->create($values);
            $this->history->record($created, HistoryEvent::Joined, (string) $values['joined_on'], null, null, $userId);

            return $created;
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function values(array $data): array
    {
        $text = fn (string $key): ?string => isset($data[$key]) && trim((string) $data[$key]) !== '' ? trim((string) $data[$key]) : null;
        $id = fn (string $key): ?int => isset($data[$key]) && $data[$key] !== '' ? (int) $data[$key] : null;
        $map = fn (string $key): ?array => is_array($data[$key] ?? null) ? array_map(fn (mixed $value): ?string => $value === null || trim((string) $value) === '' ? null : trim((string) $value), $data[$key]) : null;

        return [
            'first_name' => trim((string) $data['first_name']), 'last_name' => $text('last_name'),
            'gender' => Gender::tryFrom((string) ($data['gender'] ?? '')), 'marital_status' => MaritalStatus::tryFrom((string) ($data['marital_status'] ?? '')),
            'date_of_birth' => $text('date_of_birth'), 'nationality_code' => $text('nationality_code'), 'email' => $text('email'), 'phone' => $text('phone'),
            'address' => $map('address'), 'emergency_contact' => $map('emergency_contact'), 'passport_expiry' => $text('passport_expiry'),
            'bank_name' => $text('bank_name'), 'bank_account_name' => $text('bank_account_name'), 'bank_branch' => $text('bank_branch'),
            'department_id' => (int) $data['department_id'], 'designation_id' => (int) $data['designation_id'], 'grade_id' => $id('grade_id'), 'manager_id' => $id('manager_id'),
            'employment_type' => EmploymentType::from((string) $data['employment_type']), 'joined_on' => (string) $data['joined_on'], 'probation_ends_on' => $text('probation_ends_on'),
            'notes' => $text('notes'),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     *
     * @throws HrRuleViolated
     */
    private function check(?Employee $employee, array $values): void
    {
        $designation = Designation::query()->find($values['designation_id']);

        if (! $designation instanceof Designation || (! $designation->is_active && $employee?->designation_id !== $designation->id)) {
            throw new HrRuleViolated(__('Choose an active designation.'));
        }

        if ($values['probation_ends_on'] !== null && $values['probation_ends_on'] < $values['joined_on']) {
            throw new HrRuleViolated(__('The probation cannot end before the employee joins.'));
        }

        if ($values['manager_id'] !== null) {
            $this->checkManager($employee, (int) $values['manager_id']);
        }
    }

    /**
     * A manager must be a current employee and must not report (directly or not) to the employee.
     *
     * @throws HrRuleViolated
     */
    private function checkManager(?Employee $employee, int $managerId): void
    {
        $manager = Employee::query()->find($managerId);

        if (! $manager instanceof Employee || $manager->isExited()) {
            throw new HrRuleViolated(__('Choose a manager who works here.'));
        }

        if (! $employee instanceof Employee) {
            return;
        }

        $seen = [];

        for ($current = $manager; $current instanceof Employee; $current = $current->manager_id !== null ? Employee::query()->withTrashed()->find($current->manager_id) : null) {
            if ($current->id === $employee->id) {
                throw new HrRuleViolated(__('A manager cannot report to the employee they manage.'));
            }

            if (in_array($current->id, $seen, true)) {
                break;
            }

            $seen[] = $current->id;
        }
    }
}
