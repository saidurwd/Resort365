<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\FiscalYear;

/**
 * Creates or renames a budget (Step 7.2). A fiscal year has one budget for each property, and one for the whole company; the
 * year and property of a budget do not change once it is made.
 */
class SaveBudget extends Action
{
    /**
     * @param  array{fiscal_year_id?: int, property_id?: int|null, name: string, notes?: string|null}  $data
     *
     * @throws AccountingRuleViolated
     */
    public function handle(?Budget $budget, array $data, ?int $userId = null): Budget
    {
        if ($budget instanceof Budget) {
            $budget->forceFill(['name' => trim($data['name']), 'notes' => $data['notes'] ?? null])->save();

            return $budget;
        }

        $year = FiscalYear::query()->find($data['fiscal_year_id'] ?? 0) ?? throw new AccountingRuleViolated(__('Choose the fiscal year of the budget.'));
        $propertyId = $data['property_id'] ?? null;

        if (Budget::query()->where('fiscal_year_id', $year->id)->where('property_id', $propertyId)->exists()) {
            throw new AccountingRuleViolated(__('There is a budget for :year already.', ['year' => $year->name.($propertyId === null ? ' ('.__('whole company').')' : '')]));
        }

        return Budget::query()->create(['fiscal_year_id' => $year->id, 'property_id' => $propertyId, 'name' => trim($data['name']), 'notes' => $data['notes'] ?? null, 'created_by' => $userId]);
    }
}
