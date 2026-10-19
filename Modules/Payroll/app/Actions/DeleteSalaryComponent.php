<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\SalaryComponent;
use Modules\Payroll\Models\SalaryStructureComponent;

/**
 * Deletes a salary component that no structure uses (Step 6.4); a system component or one in use can only be made inactive.
 */
class DeleteSalaryComponent extends Action
{
    /**
     * @throws PayrollRuleViolated
     */
    public function handle(SalaryComponent $component): void
    {
        if ($component->is_system) {
            throw new PayrollRuleViolated(__('":name" is a system component, so it cannot be deleted.', ['name' => $component->name]));
        }

        if (SalaryStructureComponent::query()->where('salary_component_id', $component->id)->exists()) {
            throw new PayrollRuleViolated(__('":name" is used in a salary structure, so it can only be made inactive.', ['name' => $component->name]));
        }

        $component->delete();
    }
}
