<?php

namespace Modules\Payroll\Services;

use Modules\Payroll\DTOs\StructureLine;
use Modules\Payroll\Models\SalaryStructure;
use Modules\Payroll\Models\SalaryStructureComponent;

/**
 * Turns a stored salary structure into the lines the calculator works on (Step 6.4).
 */
class StructureLoader
{
    /**
     * @return list<StructureLine>
     */
    public function lines(SalaryStructure $structure): array
    {
        return $structure->lines()->with('component')->get()->map(fn (SalaryStructureComponent $line): StructureLine => new StructureLine(
            $line->component->code, $line->component->name, $line->component->kind, $line->component->is_taxable, $line->component->frequency, $line->component->annual_times,
            $line->calc_type, $line->amount !== null ? (string) $line->amount : null, $line->percent !== null ? (string) $line->percent : null, $line->percent_of, $line->formula,
        ))->values()->all();
    }
}
