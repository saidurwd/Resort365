<?php

namespace Modules\HR\Actions;

use App\Support\Actions\Action;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Employee;
use Modules\IAM\Contracts\UserDirectory;

/**
 * Links an employee to a sign-in account, or removes the link (Step 6.1). One account belongs to one employee.
 */
class LinkEmployeeUser extends Action
{
    public function __construct(private readonly UserDirectory $users) {}

    /**
     * @throws HrRuleViolated
     */
    public function handle(Employee $employee, ?int $userId): Employee
    {
        if ($userId !== null) {
            if (! collect($this->users->all())->contains(fn ($user): bool => $user->id === $userId)) {
                throw new HrRuleViolated(__('Choose one of this company\'s users.'));
            }

            if (Employee::query()->where('user_id', $userId)->whereKeyNot($employee->id)->exists()) {
                throw new HrRuleViolated(__('That user is already linked to another employee.'));
            }
        }

        $employee->forceFill(['user_id' => $userId])->save();

        return $employee;
    }
}
