<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use Modules\Payroll\Enums\CalcType;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\SalaryComponent;
use Modules\Payroll\Models\SalaryStructure;
use Modules\Payroll\Models\SalaryStructureComponent;
use Modules\Payroll\Services\Calculation\FormulaEvaluator;
use Modules\Payroll\Services\Calculation\SalaryCalculator;
use Modules\Payroll\Services\StructureLoader;

/**
 * Creates or changes a salary structure with its lines (Step 6.4). Each component appears once; a percentage line names the
 * component it is a percentage of, a formula is checked, and the whole structure is tried out on a sample basic salary, so a
 * circle or a missing component is refused here rather than at payroll time.
 */
class SaveSalaryStructure extends Action
{
    public function __construct(
        private readonly FormulaEvaluator $formulas,
        private readonly SalaryCalculator $calculator,
        private readonly StructureLoader $loader,
    ) {}

    /**
     * @param  array<string, mixed>  $data  code, name, description, is_active, lines: list of component_id, calc_type, amount, percent, percent_of, formula
     *
     * @throws PayrollRuleViolated
     */
    public function handle(?SalaryStructure $structure, array $data): SalaryStructure
    {
        $code = strtoupper(trim((string) $data['code']));

        if (SalaryStructure::query()->withTrashed()->where('code', $code)->when($structure instanceof SalaryStructure, fn ($query) => $query->whereKeyNot($structure?->id))->exists()) {
            throw new PayrollRuleViolated(__('There is already a structure with the code :code.', ['code' => $code]));
        }

        $lines = $this->lines((array) ($data['lines'] ?? []));

        return $this->transaction(function () use ($structure, $data, $code, $lines): SalaryStructure {
            $structure ??= new SalaryStructure;
            $structure->fill(['code' => $code, 'name' => trim((string) $data['name']), 'description' => isset($data['description']) && trim((string) $data['description']) !== '' ? trim((string) $data['description']) : null, 'is_active' => (bool) ($data['is_active'] ?? true)])->save();
            $structure->lines()->delete();

            foreach ($lines as $order => $line) {
                SalaryStructureComponent::query()->create($line + ['salary_structure_id' => $structure->id, 'sort_order' => ($order + 1) * 10]);
            }

            $this->tryOut($structure->load('lines'));

            return $structure;
        });
    }

    /**
     * @param  array<int|string, mixed>  $rows
     * @return list<array<string, mixed>>
     *
     * @throws PayrollRuleViolated
     */
    private function lines(array $rows): array
    {
        $lines = [];
        $seen = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $component = SalaryComponent::query()->find((int) ($row['salary_component_id'] ?? $row['component_id'] ?? 0));

            if (! $component instanceof SalaryComponent) {
                throw new PayrollRuleViolated(__('Choose a component for every line.'));
            }

            if (isset($seen[$component->id])) {
                throw new PayrollRuleViolated(__('":name" is in the structure twice.', ['name' => $component->name]));
            }

            $seen[$component->id] = true;
            $type = CalcType::tryFrom((string) ($row['calc_type'] ?? '')) ?? throw new PayrollRuleViolated(__('Choose how ":name" is worked out.', ['name' => $component->name]));
            $line = ['salary_component_id' => $component->id, 'calc_type' => $type, 'amount' => null, 'percent' => null, 'percent_of' => null, 'formula' => null];

            match ($type) {
                CalcType::Fixed, CalcType::Employee => $line['amount'] = $this->number($row['amount'] ?? '0', $component->name),
                CalcType::Percent => $line = [...$line, 'percent' => $this->number($row['percent'] ?? '', $component->name), 'percent_of' => strtoupper(trim((string) ($row['percent_of'] ?? '')))],
                CalcType::Formula => $line['formula'] = trim((string) ($row['formula'] ?? '')),
            };

            if ($type === CalcType::Percent && $line['percent_of'] === '') {
                throw new PayrollRuleViolated(__('Say what ":name" is a percentage of.', ['name' => $component->name]));
            }

            if ($type === CalcType::Formula) {
                $this->formulas->references((string) $line['formula']);
            }

            $lines[] = $line;
        }

        if ($lines === []) {
            throw new PayrollRuleViolated(__('A structure needs at least one component.'));
        }

        return $lines;
    }

    private function number(mixed $value, string $name): string
    {
        if (! is_numeric($value) || (float) $value < 0) {
            throw new PayrollRuleViolated(__('Enter a number for ":name".', ['name' => $name]));
        }

        return (string) $value;
    }

    /**
     * @throws PayrollRuleViolated
     */
    private function tryOut(SalaryStructure $structure): void
    {
        $this->calculator->calculate($this->loader->lines($structure), '10000', null);
    }
}
