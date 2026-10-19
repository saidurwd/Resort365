<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use Modules\Payroll\Enums\ComponentFrequency;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\SalaryComponent;

/**
 * Creates or changes a salary component (Step 6.4). Codes are letters, digits and underscores, unique per tenant; the code and
 * kind of a system component (BASIC, TAX…) cannot be changed, because the calculation and the payroll run rely on them.
 */
class SaveSalaryComponent extends Action
{
    /**
     * @param  array<string, mixed>  $data  code, name, kind, is_taxable, frequency, annual_times, sort_order, is_active
     *
     * @throws PayrollRuleViolated
     */
    public function handle(?SalaryComponent $component, array $data): SalaryComponent
    {
        $code = strtoupper(trim((string) $data['code']));
        $kind = ComponentKind::from((string) $data['kind']);

        if (preg_match('/^[A-Z][A-Z0-9_]{0,19}$/', $code) !== 1) {
            throw new PayrollRuleViolated(__('A code starts with a letter and has only capital letters, digits and underscores.'));
        }

        if (SalaryComponent::query()->withTrashed()->where('code', $code)->when($component instanceof SalaryComponent, fn ($query) => $query->whereKeyNot($component?->id))->exists()) {
            throw new PayrollRuleViolated(__('There is already a component with the code :code.', ['code' => $code]));
        }

        if ($component instanceof SalaryComponent && $component->is_system && ($component->code !== $code || $component->kind !== $kind)) {
            throw new PayrollRuleViolated(__('The code and kind of ":name" cannot be changed: payroll relies on them.', ['name' => $component->name]));
        }

        $frequency = ComponentFrequency::tryFrom((string) ($data['frequency'] ?? '')) ?? ComponentFrequency::Monthly;
        $times = max(1, min(12, (int) ($data['annual_times'] ?? 1)));

        if ($frequency === ComponentFrequency::Annual && $kind !== ComponentKind::Earning) {
            throw new PayrollRuleViolated(__('Only earnings can be paid a few times a year.'));
        }

        $component ??= new SalaryComponent;
        $component->fill([
            'code' => $code, 'name' => trim((string) $data['name']), 'kind' => $kind, 'is_taxable' => $kind === ComponentKind::Earning && (bool) ($data['is_taxable'] ?? false), 'frequency' => $frequency,
            'annual_times' => $frequency === ComponentFrequency::Annual ? $times : 1, 'sort_order' => (int) ($data['sort_order'] ?? 100), 'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        if (! $component->exists) {
            $component->is_system = false;
        }

        $component->save();

        return $component;
    }
}
